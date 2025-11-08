<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Медицинская аналитика СМП | Карта вызовов</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #3f8a43;
            --secondary: #4caf50;
            --accent: #75e071;
            --gradient: linear-gradient(155deg, var(--primary), var(--secondary));
            --glass-bg: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.1));
            --glass-border: rgba(255,255,255,0.25);
            --glass-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            overflow-x: hidden;
        }

        .start-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
        }

        .start-screen.hidden {
            display: none;
        }

        .start-content {
            text-align: center;
            background: var(--glass-bg);
            padding: 3rem 4rem;
            border-radius: 25px;
            backdrop-filter: blur(25px);
            border: 2px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            position: relative;
            overflow: hidden;
            min-width: 600px;
            min-height: 400px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 2;
        }

        .start-content::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent,
                rgba(255,255,255,0.1),
                transparent
            );
            animation: shimmer 3s infinite linear;
            z-index: -1;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .start-title {
            font-size: 2.8rem;
            margin-bottom: 1rem;
            font-weight: 700;
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 2;
        }

        .start-subtitle {
            font-size: 1.4rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
            line-height: 1.5;
        }

        .open-map-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 1rem 2.5rem;
            font-size: 1.3rem;
            border-radius: 25px;
            cursor: pointer;
            margin-top: 1.5rem;
            transition: all 0.4s;
            font-weight: 600;
            backdrop-filter: blur(10px);
            animation: pulse 2s infinite;
            position: relative;
            z-index: 2;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(255,255,255,0.2); }
            50% { transform: scale(1.05); box-shadow: 0 0 30px rgba(255,255,255,0.4); }
        }

        .open-map-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            animation: none;
        }
     
        .header {
            background: var(--gradient);
            color: white;
            border-radius: 0 0 50px 50px;
            padding: 3.5rem 0;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="rgba(255,255,255,0.1)"><circle cx="50" cy="50" r="2"/></svg>');
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(0) translateX(0); }
            100% { transform: translateY(-100px) translateX(100px); }
        }

        .header h1 {
            font-size: 3.2rem;
            margin-bottom: 1rem;
            font-weight: 700;
            position: relative;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .header p {
            font-size: 1.4rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            position: relative;
        }

        .header-controls {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
            position: relative;
        }

        .nav-button {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid white;
            padding: 1rem 2rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.1rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .nav-button:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.2);
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2.5rem;
            border-radius: 50px;
            width: 90%;
            max-width: 550px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            transform: translateY(20px);
            animation: modalAppear 0.4s ease-out forwards;
        }

        @keyframes modalAppear {
            to { transform: translateY(0); opacity: 1; }
        }

        .close-modal {
            float: right;
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #666;
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: #333;
        }

        .file-input {
            width: 100%;
            padding: 1.2rem;
            border: 3px dashed var(--primary);
            border-radius: 50px;
            margin: 1.5rem 0;
            text-align: center;
            font-size: 1.1rem;
            transition: all 0.3s;
        }

        .file-input:hover {
            background: rgba(33, 150, 243, 0.05);
            border-color: var(--gradient);
        }

        .upload-button {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 1.3rem;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: 600;
            transition: all 0.3s;
        }

        .upload-button:hover {
            background: var(--gradient);
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(31, 142, 233, 0.3);
        }

        .main-content {
            padding: 3rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 50px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
            transition: all 0.4s;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: #75e071;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #000000;
            margin: 1rem 0;
        }

        .stat-label {
            color: #000000;
            font-size: 1.3rem;
            font-weight: 500;
        }

        .map-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            height: 650px;
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        #heatMap {
            height: 100%;
            width: 100%;
        }

        .map-legend {
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            border: 1px solid rgba(0, 0, 0, 0.1);
            max-width: 250px;
        }

        @media (max-width: 768px) {
            .map-legend {
                max-width: 200px;
                padding: 1rem;
                top: 10px;
                right: 10px;
            }
            
            .legend-item {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .map-legend {
                position: relative;
                top: auto;
                right: auto;
                max-width: 100%;
                margin: 10px;
                border-radius: 10px;
            }
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.8rem;
            font-size: 1rem;
        }

        .legend-color {
            width: 22px;
            height: 22px;
            margin-right: 0.8rem;
            border-radius: 50px;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .legend-color.high-intensity { background: #ff0000; }
        .legend-color.medium-intensity { background: #ffa500; }
        .legend-color.low-intensity { background: #008000; }

        .notification {
            position: fixed;
            top: 25px;
            right: 25px;
            background: white;
            padding: 1.2rem 1.8rem;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-left: 5px solid var(--accent);
            display: none;
            z-index: 2000;
            max-width: 450px;
            font-size: 1.1rem;
        }

        .notification.show {
            display: block;
            animation: slideIn 0.4s ease-out;
        }

        .notification.success { border-left-color: #27ae60; }
        .notification.error { border-left-color: #e74c3c; }
        .notification.warning { border-left-color: #f39c12; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .loading {
            display: inline-block;
            width: 25px;
            height: 25px;
            border: 4px solid #f3f3f3;
            border-radius: 50%;
            border-top-color: var(--accent);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .sector-controls {
            position: absolute;
            top: 10px;
            left: 60px;
            background: white;
            padding: 1.5rem;
            border-radius: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            border: 1px solid rgba(0, 0, 0, 0.1);
            max-width: 280px;
            transition: all 0.3s ease;  
        }

        .sector-controls h4 {
            margin-bottom: 1rem;
            color: #333;
            font-size: 1.6rem;
            font-weight: 700;
            text-align: center;
        }

        .sector-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.6rem;
        }

        .sector-btn {
            background: var(--gradient);
            color: white;
            border: none;
            padding: 0.5rem 0.8rem;
            border-radius: 30px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 35px;
        }

        .sector-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
        }

        .sector-btn.active {
            background: var(--primary);
            transform: scale(1.03);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
        }

        .compact-modal {
            max-width: 500px;
            max-height: 85vh;
            overflow-y: auto;
        }

        .filters-compact-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin: 1rem 0;
        }

        .filters-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .filter-group-compact {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .filter-group-compact h3 {
            margin: 0 0 0.8rem 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .filter-group-full {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            grid-column: 1 / -1;
        }

        .filter-group-full h3 {
            margin: 0 0 0.8rem 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .compact-inputs {
            display: flex;
            gap: 0.5rem;
        }

        .compact-input, .compact-select {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.85rem;
            background: white;
        }

        .compact-input:focus, .compact-select:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
        }

        .sectors-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        .sector-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 0.85rem;
        }

        .sector-checkbox:hover {
            background: rgba(52, 152, 219, 0.1);
        }

        .sector-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--accent);
        }

        .filter-actions-compact {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
            position: sticky;
            bottom: 0;
            background: white;
            padding-bottom: 0.5rem;
        }

        .filter-btn.compact-btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            min-width: 120px;
            justify-content: center;
        }

        .filter-btn.compact-btn.primary {
            background: var(--primary);
            color: white;
        }

        .filter-btn.compact-btn.primary:hover {
            background: var(--secondary);
            transform: translateY(-1px);
        }

        .filter-btn.compact-btn.secondary {
            background: #6c757d;
            color: white;
        }

        .filter-btn.compact-btn.secondary:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        @media (max-width: 600px) {
            .compact-modal {
                max-width: 95%;
                margin: 1rem;
            }
            
            .filters-column {
                grid-template-columns: 1fr;
            }
            
            .sectors-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions-compact {
                flex-direction: column;
            }
            
            .filter-btn.compact-btn {
                min-width: auto;
                width: 100%;
            }
        }

        .progress-bar {
            display: none;
            margin-top: 1.5rem;
        }

        .progress-bar-fill {
            height: 25px;
            background: var(--accent);
            width: 0%;
            transition: width 0.4s;
            border-radius: 12px;
        }

        .progress-text {
            text-align: center;
            margin-top: 0.8rem;
            font-size: 1.1rem;
        }

        .footer {
            background: var(--gradient);
            color: white;
            padding: 3rem 0;
            text-align: center;
            margin-top: 4rem;
            border-radius: 50px 50px 0 0;
            box-shadow: 0 -8px 25px rgba(76, 175, 80, 0.2);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .contact-info h3 {
            margin-bottom: 2rem;
            color: #e8f5e9;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .contact-info p {
            margin-bottom: 1rem;
            opacity: 0.9;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 2.5rem;
            flex-wrap: wrap;
        }

        .footer-link {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid white;
            padding: 1rem 2rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.1rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .footer-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.2);
        }

        .logo-icon {
            font-size: 1.3em;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        @media (max-width: 768px) {
            .start-title {
                font-size: 2.5rem;
            }
            
            .start-subtitle {
                font-size: 1.2rem;
            }
            
            .header h1 {
                font-size: 2.5rem;
            }
            
            .header p {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 480px) {
            .start-content {
                padding: 2rem;
                margin: 1rem;
                min-width: auto;
                min-height: auto;
            }
            
            .start-title {
                font-size: 2rem;
            }
            
            .start-subtitle {
                font-size: 1rem;
            }
            
            .open-map-btn {
                padding: 1rem 2rem;
                font-size: 1.1rem;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .header p {
                font-size: 1rem;
            }
            
            .nav-button {
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="start-screen" id="startScreen">
        <div class="start-content">
            <h1 class="start-title"><i class="fas fa-ambulance logo-icon"></i> Медицинская аналитика СМП</h1>
            <p class="start-subtitle">Интеллектуальная система аккуализации<br>вызовов скорой помощи</p>
            <button class="open-map-btn" onclick="openMap()">
                <i class="fas fa-map-marked-alt"></i> Открыть карту
            </button>
        </div>
    </div>

    <header class="header" style="display: none;" id="mainHeader">
        <h1><i class="fas fa-heartbeat logo-icon"></i> Система медицинской аналитики СМП</h1>
        <p>Мониторинг вызовов скорой медицинской помощи в реальном времени</p>
        <div class="header-controls">
            <button class="nav-button" onclick="openModal('uploadModal')">
                <i class="fas fa-upload"></i> Загрузить данные
            </button>
            <button class="nav-button" onclick="loadMapData()">
                <i class="fas fa-sync-alt"></i> Обновить карту
            </button>
            <button class="nav-button" onclick="openModal('filtersModal')">
                <i class="fas fa-filter"></i> Фильтры анализа
            </button>
            <button class="nav-button" onclick="openModal('searchModal')">
                <i class="fas fa-search-location"></i> Поиск по координатам
            </button>
        </div>
    </header>

    <div class="modal-overlay" id="uploadModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal('uploadModal')">×</button>
            <h2 style="font-size: 1.8rem; margin-bottom: 1rem;"><i class="fas fa-database logo-icon"></i> Загрузка данных СМП</h2>
            <p style="font-size: 1.2rem; margin-bottom: 1.5rem;">Загрузите Excel файл с данными вызовов скорой помощи</p>
            <input type="file" id="fileUpload" class="file-input" accept=".xlsx,.xls">
            <button class="upload-button" onclick="uploadFile()">
                <i class="fas fa-cloud-upload-alt"></i> Загрузить и обработать данные
            </button>
            <div class="progress-bar">
                <div class="progress-bar-fill"></div>
                <div class="progress-text"></div>
            </div>
            <div id="uploadProgress" style="display: none; text-align: center; margin-top: 1.5rem;">
                <div class="loading"></div>
                <p style="font-size: 1.1rem; margin-top: 1rem;">Обработка данных и создание таблицы...</p>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="filtersModal">
        <div class="modal-content compact-modal">
            <button class="close-modal" onclick="closeModal('filtersModal')">×</button>
            <h2 style="font-size: 1.5rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-filter"></i> Фильтры анализа
            </h2>
            
            <div class="filters-compact-grid">
                <div class="filters-column">
                    <div class="filter-group-compact">
                        <h3><i class="fas fa-calendar"></i> Дата</h3>
                        <div class="compact-inputs">
                            <input type="date" id="startDate" class="compact-input" placeholder="Начало">
                            <input type="date" id="endDate" class="compact-input" placeholder="Окончание">
                        </div>
                    </div>

                    <div class="filter-group-compact">
                        <h3><i class="fas fa-clock"></i> Время суток</h3>
                        <select id="timeOfDayFilter" class="compact-select">
                            <option value="all">Любое время</option>
                            <option value="morning">Утро (06:00-11:59)</option>
                            <option value="day">День (12:00-17:59)</option>
                            <option value="evening">Вечер (18:00-23:59)</option>
                            <option value="night">Ночь (00:00-05:59)</option>
                        </select>
                    </div>

                    <div class="filter-group-compact">
                        <h3><i class="fas fa-stethoscope"></i> Причина вызова</h3>
                        <select id="reasonFilter" class="compact-select">
                            <option value="all">Все причины</option>
                        </select>
                    </div>
                </div>

                <div class="filters-column">
                    <div class="filter-group-compact">
                        <h3><i class="fas fa-user"></i> Возраст</h3>
                        <div class="compact-inputs">
                            <input type="number" id="minAge" class="compact-input" placeholder="От" min="0" max="120">
                            <input type="number" id="maxAge" class="compact-input" placeholder="До" min="0" max="120">
                        </div>
                    </div>

                    <div class="filter-group-compact">
                        <h3><i class="fas fa-fire"></i> Интенсивность</h3>
                        <select id="intensityFilter" class="compact-select">
                            <option value="all">Все</option>
                            <option value="high">Высокая</option>
                            <option value="medium">Средняя</option>
                            <option value="low">Низкая</option>
                        </select>
                    </div>

                    <div class="filter-group-compact">
                        <h3><i class="fas fa-file-medical"></i> Диагноз по МКБ</h3>
                        <select id="diagnosisFilter" class="compact-select">
                            <option value="all">Все диагнозы</option>
                        </select>
                    </div>
                </div>

                <div class="filter-group-full">
                    <h3><i class="fas fa-layer-group"></i> Сектора</h3>
                    <div class="sectors-grid">
                        <label class="sector-checkbox">
                            <input type="checkbox" name="sector" value="1" checked>
                            <span class="checkmark"></span>
                            Сектор 1
                        </label>
                        <label class="sector-checkbox">
                            <input type="checkbox" name="sector" value="2" checked>
                            <span class="checkmark"></span>
                            Сектор 2
                        </label>
                        <label class="sector-checkbox">
                            <input type="checkbox" name="sector" value="3" checked>
                            <span class="checkmark"></span>
                            Сектор 3
                        </label>
                        <label class="sector-checkbox">
                            <input type="checkbox" name="sector" value="4" checked>
                            <span class="checkmark"></span>
                            Сектор 4
                        </label>
                    </div>
                </div>
            </div>

            <div class="filter-actions-compact">
                <button class="filter-btn compact-btn secondary" onclick="resetFilters()">
                    <i class="fas fa-undo"></i> Сбросить
                </button>
                <button class="filter-btn compact-btn primary" onclick="applyFilters()">
                    <i class="fas fa-check"></i> Применить фильтры
                </button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="searchModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal('searchModal')">×</button>
            <h2 style="font-size: 1.8rem; margin-bottom: 1rem;">
                <i class="fas fa-search-location logo-icon"></i> Поиск по координатам
            </h2>
            
            <div class="search-coordinates">
                <p style="margin-bottom: 1.5rem; color: #666;">
                    Введите точные координаты для поиска вызова на карте
                </p>
                
                <div class="coord-inputs">
                    <div class="input-group">
                        <label>Широта:</label>
                        <input type="text" id="searchLat" class="filter-input" 
                               placeholder="46.9587 или +46.9587" 
                               pattern="[-+]?[0-9]*[.,]?[0-9]+"
                               title="Введите число, можно со знаком + или -">
                    </div>
                    <div class="input-group">
                        <label>Долгота:</label>
                        <input type="text" id="searchLng" class="filter-input" 
                               placeholder="142.7360 или +142.7360"
                               pattern="[-+]?[0-9]*[.,]?[0-9]+"
                               title="Введите число, можно со знаком + или -">
                    </div>
                </div>

                <div class="search-tip">
                    <i class="fas fa-info-circle"></i>
                    Координаты должны быть в пределах Сахалинской области
                </div>

                <button class="search-coord-btn" onclick="searchByExactCoordinates()">
                    <i class="fas fa-crosshairs"></i> Найти на карте
                </button>
            </div>
        </div>
    </div>

    <main class="main-content" style="display: none;" id="mainContent">
        <div class="stats-cards">
            <div class="stat-card">
                <i class="fas fa-ambulance"></i>
                <div class="stat-value" id="totalCalls">0</div>
                <div class="stat-label">Всего вызовов</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-map-marker-alt"></i>
                <div class="stat-value" id="coveredAreas">0</div>
                <div class="stat-label">Охваченные районы</div>
            </div>
        </div>

        <div class="map-container">
            <div id="heatMap"></div>
            <div class="map-legend">
                <h4 style="font-size: 1.3rem; margin-bottom: 1.2rem;"><i class="fas fa-fire"></i> Интенсивность вызовов</h4>
                <div class="legend-item">
                    <div class="legend-color high-intensity"></div>
                    <span>Высокая интенсивность</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color medium-intensity"></div>
                    <span>Средняя интенсивность</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color low-intensity"></div>
                    <span>Низкая интенсивность</span>
                </div>
            </div>

            <div class="sector-controls">
                <h4><i class="fas fa-layer-group"></i> Сектора</h4>
                <div class="sector-buttons">
                    <button class="sector-btn active" onclick="filterBySector(0, this)">Все сектора</button>
                    <button class="sector-btn" onclick="filterBySector(1, this)">Сектор 1</button>
                    <button class="sector-btn" onclick="filterBySector(2, this)">Сектор 2</button>
                    <button class="sector-btn" onclick="filterBySector(3, this)">Сектор 3</button>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer" id="footer">
        <div class="footer-content">
            <div class="contact-info">
                <h3><i class="fas fa-headset logo-icon"></i> Контактная информация</h3>
                <p><i class="fas fa-phone"></i> Телефон: +7 (4242) 55-XX-XX</p>
                <p><i class="fas fa-envelope"></i> Email: smp@medsakhalin.ru</p>
                <p><i class="fas fa-map-marker-alt"></i> Адрес: г. Южно-Сахалинск, ул. Ленина, 1</p>
            </div>
        </div>
    </footer>

    <div class="notification" id="notification"></div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        let map;
        let markers = [];
        let currentSector = 0;
        const API_BASE_URL = 'api.php';
        const SOUTH_SAKHALIN_CENTER = [46.9587, 142.7360];

        function openMap() {
            document.getElementById('startScreen').classList.add('hidden');
            document.getElementById('mainHeader').style.display = 'block';
            document.getElementById('mainContent').style.display = 'block';
            document.getElementById('footer').style.display = 'block';

            initializeMap();
            loadStats();
            loadMapData();
        }

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function initializeMap() {
            if (map) {
                map.remove();
            }

            map = L.map('heatMap').setView(SOUTH_SAKHALIN_CENTER, 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(map);
        }

        async function loadStats() {
            try {
                const response = await fetch(`/api.php?action=get_stats`);
                const data = await response.json();

                if (data.success) {
                    document.getElementById('totalCalls').textContent = data.stats.totalCalls.toLocaleString();
                    document.getElementById('coveredAreas').textContent = data.stats.sectorsCovered;
                }
            } catch (error) {
                console.error('Ошибка загрузки статистики:', error);
                showNotification('Ошибка загрузки статистики', 'error');
            }
        }

        async function loadMapData() {
            try {
                clearMarkers();
                showNotification('Загрузка данных карты...', 'info');

                const response = await fetch(`/api.php?action=get_calls`);
                const data = await response.json();

                if (data.success && data.calls.length > 0) {
                    data.calls.forEach(call => {
                        if (call.coordinates && call.coordinates[0] && call.coordinates[1]) {
                            const marker = L.circleMarker([call.coordinates[1], call.coordinates[0]], {
                                radius: 10,
                                fillColor: getColorForIntensity(call.intensity),
                                color: '#000',
                                weight: 1.5,
                                opacity: 0.9,
                                fillOpacity: 0.8
                            }).addTo(map);

                            marker.bindPopup(`
                                <div style="min-width: 280px;">
                                    <h3 style="color: var(--accent); margin-bottom: 12px; border-bottom: 2px solid #eee; padding-bottom: 8px;">
                                        <i class="fas fa-ambulance"></i> Вызов СМП
                                    </h3>
                                    <p><strong><i class="fas fa-fire"></i> Интенсивность:</strong> 
                                        <span style="color: ${getColorForIntensity(call.intensity)}; font-weight: bold;">
                                            ${getIntensityText(call.intensity)}
                                        </span>
                                    </p>
                                    <p><strong><i class="fas fa-map"></i> Сектор:</strong> ${call.sector || 'Не указан'}</p>
                                    <p><strong><i class="fas fa-stethoscope"></i> Причина:</strong> ${call.reason || 'Не указана'}</p>
                                    <p><strong><i class="fas fa-user"></i> Возраст:</strong> ${call.age || 'Не указан'}</p>
                                    <p><strong><i class="fas fa-venus-mars"></i> Пол:</strong> ${call.gender || 'Не указан'}</p>
                                    <hr style="margin: 12px 0;">
                                    <small><i class="fas fa-map-marker-alt"></i> ${call.coordinates[1].toFixed(6)}, ${call.coordinates[0].toFixed(6)}</small>
                                </div>
                            `);

                            marker.sector = call.sector;
                            marker.visible = true;
                            markers.push(marker);
                        }
                    });

                    if (markers.length > 0) {
                        const group = new L.featureGroup(markers.filter(m => m.visible));
                        map.fitBounds(group.getBounds().pad(0.1));
                    }

                    showNotification(`Загружено ${markers.length} вызовов СМП`, 'success');
                } else {
                    showNotification('Нет данных для отображения. Загрузите данные через форму загрузки.', 'warning');
                }
            } catch (error) {
                console.error('Ошибка загрузки данных карты:', error);
                showNotification('Ошибка загрузки данных. Проверьте подключение к БД.', 'error');
            }
        }

        async function loadFilterOptions() {
            try {
                const response = await fetch('/api.php?action=get_filters');
                const data = await response.json();
                
                if (data.success) {
                    const reasonSelect = document.getElementById('reasonFilter');
                    data.filters.reasons.forEach(reason => {
                        if (reason) {
                            const option = document.createElement('option');
                            option.value = reason;
                            option.textContent = reason;
                            reasonSelect.appendChild(option);
                        }
                    });
                    
                    const diagnosisSelect = document.getElementById('diagnosisFilter');
                    data.filters.diagnoses.forEach(diagnosis => {
                        if (diagnosis) {
                            const option = document.createElement('option');
                            option.value = diagnosis;
                            option.textContent = diagnosis;
                            diagnosisSelect.appendChild(option);
                        }
                    });
                }
            } catch (error) {
                console.error('Ошибка загрузки опций фильтров:', error);
            }
        }

        function applyFilters() {
            const params = new URLSearchParams();
            
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const reason = document.getElementById('reasonFilter').value;
            const diagnosis = document.getElementById('diagnosisFilter').value;
            const minAge = document.getElementById('minAge').value;
            const maxAge = document.getElementById('maxAge').value;
            const timeOfDay = document.getElementById('timeOfDayFilter').value;
            const intensity = document.getElementById('intensityFilter').value;
            
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);
            if (reason && reason !== 'all') params.append('reason', reason);
            if (diagnosis && diagnosis !== 'all') params.append('diagnosis', diagnosis);
            if (minAge) params.append('min_age', minAge);
            if (maxAge) params.append('max_age', maxAge);
            if (timeOfDay && timeOfDay !== 'all') params.append('time_of_day', timeOfDay);
            if (intensity && intensity !== 'all') params.append('intensity', intensity);
            
            const selectedSectors = Array.from(document.querySelectorAll('input[name="sector"]:checked'))
                .map(checkbox => checkbox.value);
            if (selectedSectors.length > 0) {
                params.append('sectors', selectedSectors.join(','));
            }
            
            closeModal('filtersModal');
            loadMapDataWithFilters(params.toString());
        }

        async function loadMapDataWithFilters(filterParams = '') {
            try {
                clearMarkers();
                showNotification('Применение фильтров...', 'info');
                
                const url = filterParams ? `/api.php?action=get_calls&${filterParams}` : '/api.php?action=get_calls';
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success && data.calls.length > 0) {
                    data.calls.forEach(call => {
                        if (call.coordinates && call.coordinates[0] && call.coordinates[1]) {
                            const marker = L.circleMarker([call.coordinates[1], call.coordinates[0]], {
                                radius: 10,
                                fillColor: getColorForIntensity(call.intensity),
                                color: '#000',
                                weight: 1.5,
                                opacity: 0.9,
                                fillOpacity: 0.8
                            }).addTo(map);

                            marker.bindPopup(`
                                <div style="min-width: 280px;">
                                    <h3 style="color: var(--accent); margin-bottom: 12px; border-bottom: 2px solid #eee; padding-bottom: 8px;">
                                        <i class="fas fa-ambulance"></i> Вызов СМП
                                    </h3>
                                    <p><strong><i class="fas fa-fire"></i> Интенсивность:</strong> 
                                        <span style="color: ${getColorForIntensity(call.intensity)}; font-weight: bold;">
                                            ${getIntensityText(call.intensity)}
                                        </span>
                                    </p>
                                    <p><strong><i class="fas fa-map"></i> Сектор:</strong> ${call.sector || 'Не указан'}</p>
                                    <p><strong><i class="fas fa-stethoscope"></i> Причина:</strong> ${call.reason || 'Не указана'}</p>
                                    <p><strong><i class="fas fa-user"></i> Возраст:</strong> ${call.age || 'Не указан'}</p>
                                    <p><strong><i class="fas fa-venus-mars"></i> Пол:</strong> ${call.gender || 'Не указан'}</p>
                                    <p><strong><i class="fas fa-file-medical"></i> Диагноз:</strong> ${call.diagnosis || 'Не указан'}</p>
                                    <hr style="margin: 12px 0;">
                                    <small><i class="fas fa-map-marker-alt"></i> ${call.coordinates[1].toFixed(6)}, ${call.coordinates[0].toFixed(6)}</small>
                                </div>
                            `);

                            marker.sector = call.sector;
                            marker.visible = true;
                            markers.push(marker);
                        }
                    });

                    if (markers.length > 0) {
                        const group = new L.featureGroup(markers.filter(m => m.visible));
                        map.fitBounds(group.getBounds().pad(0.1));
                    }

                    showNotification(`Загружено ${markers.length} вызовов с примененными фильтрами`, 'success');
                } else {
                    showNotification('Нет данных, соответствующих выбранным фильтрам', 'warning');
                }
            } catch (error) {
                console.error('Ошибка загрузки данных с фильтрами:', error);
                showNotification('Ошибка применения фильтров', 'error');
            }
        }

        function resetFilters() {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            document.getElementById('reasonFilter').value = 'all';
            document.getElementById('diagnosisFilter').value = 'all';
            document.getElementById('minAge').value = '';
            document.getElementById('maxAge').value = '';
            document.getElementById('timeOfDayFilter').value = 'all';
            document.getElementById('intensityFilter').value = 'all';
            
            document.querySelectorAll('input[name="sector"]').forEach(checkbox => {
                checkbox.checked = true;
            });
            
            showNotification('Фильтры сброшены', 'info');
        }

        let coordinateMarker = null;

        async function searchByExactCoordinates() {
            const latInput = document.getElementById('searchLat').value;
            const lngInput = document.getElementById('searchLng').value;
            
            if (!latInput || !lngInput) {
                showNotification('Введите координаты для поиска', 'warning');
                return;
            }
            
            function parseCoordinateWithSign(coord) {
                let cleaned = coord.toString().trim();
                
                cleaned = cleaned.replace(/,/g, '.');
                
                const hasPlus = cleaned.startsWith('+');
                const hasMinus = cleaned.startsWith('-');
                
                let numberPart = cleaned;
                if (hasPlus || hasMinus) {
                    numberPart = cleaned.substring(1);
                }
                
                const parsed = parseFloat(numberPart);
                
                if (isNaN(parsed)) {
                    return null;
                }
                
                if (hasMinus) {
                    return -parsed;
                } else if (hasPlus) {
                    return parsed; 
                } else {
                    return parsed; 
                }
            }
            
            const lat = parseCoordinateWithSign(latInput);
            const lng = parseCoordinateWithSign(lngInput);
            
            console.log('Введенные координаты:', latInput, lngInput);
            console.log('Распарсенные координаты:', lat, lng);
            
            if (lat === null || lng === null) {
                showNotification('Некорректный формат координат. Используйте: 46.9587 или +46.9587 или -46.9587', 'warning');
                return;
            }
            
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                showNotification('Координаты вне допустимого диапазона', 'warning');
                return;
            }
            
            try {
                showNotification('Поиск вызова по координатам...', 'info');
                
                const response = await fetch(`/api.php?action=search_by_exact_coordinates&lat=${lat}&lng=${lng}`);
                const data = await response.json();
                
                if (data.success) {
                    if (coordinateMarker) {
                        map.removeLayer(coordinateMarker);
                    }
                    
                    if (data.found) {
                        coordinateMarker = L.circleMarker([lat, lng], {
                            radius: 12,
                            fillColor: '#ff0000',
                            color: '#000',
                            weight: 3,
                            opacity: 1,
                            fillOpacity: 0.9
                        }).addTo(map);
                        
                        coordinateMarker.bindPopup(`
                            <div style="min-width: 300px;">
                                <h3 style="color: #ff0000; margin-bottom: 12px; border-bottom: 2px solid #eee; padding-bottom: 8px;">
                                    <i class="fas fa-crosshairs"></i> Найденный вызов
                                </h3>
                                <p><strong>Координаты:</strong> ${lat}°, ${lng}°</p>
                                <p><strong>Интенсивность:</strong> ${getIntensityText(data.call.intensity)}</p>
                                <p><strong>Причина:</strong> ${data.call.reason || 'Не указана'}</p>
                                <p><strong>Сектор:</strong> ${data.call.sector || 'Не указан'}</p>
                                <p><strong>Адрес:</strong> ${data.call.address || 'Не указан'}</p>
                                <p><strong>Дата/время:</strong> ${data.call.date || 'Не указана'} ${data.call.time || ''}</p>
                                <p><strong>Возраст:</strong> ${data.call.age || 'Не указан'}</p>
                                <p><strong>Пол:</strong> ${data.call.gender || 'Не указан'}</p>
                                <p><strong>Диагноз:</strong> ${data.call.diagnosis || 'Не указан'}</p>
                            </div>
                        `).openPopup();
                        
                        map.setView([lat, lng], 16);
                        
                        showNotification('Вызов найден по указанным координатам', 'success');
                        closeModal('searchModal');
                        
                    } else {
                        coordinateMarker = L.circleMarker([lat, lng], {
                            radius: 8,
                            fillColor: '#999',
                            color: '#000',
                            weight: 2,
                            opacity: 1,
                            fillOpacity: 0.7
                        }).addTo(map);
                        
                        coordinateMarker.bindPopup(`
                            <div style="text-align: center;">
                                <h4><i class="fas fa-map-marker-alt"></i> Указанные координаты</h4>
                                <p>${lat}°, ${lng}°</p>
                                <p><em>Вызовов не найдено</em></p>
                            </div>
                        `).openPopup();
                        
                        map.setView([lat, lng], 16);
                        showNotification('На указанных координатах вызовов не найдено', 'warning');
                        closeModal('searchModal');
                    }
                    
                } else {
                    showNotification('Ошибка поиска: ' + data.message, 'error');
                }
                
            } catch (error) {
                console.error('Ошибка поиска по координатам:', error);
                showNotification('Ошибка при поиске по координатам', 'error');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadFilterOptions();
            console.log('Система медицинской аналитики СМП готова к работе');
        });

        function filterBySector(sector, button) {
            currentSector = sector;
            document.querySelectorAll('.sector-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            button.classList.add('active');

            markers.forEach(marker => {
                if (sector === 0 || marker.sector === sector) {
                    if (!marker.visible) {
                        map.addLayer(marker);
                        marker.visible = true;
                    }
                } else {
                    if (marker.visible) {
                        map.removeLayer(marker);
                        marker.visible = false;
                    }
                }
            });

            const visibleMarkers = markers.filter(m => m.visible);
            if (visibleMarkers.length > 0) {
                const group = new L.featureGroup(visibleMarkers);
                map.fitBounds(group.getBounds().pad(0.1));
            }

            showNotification(`Показаны вызовы: ${sector === 0 ? 'Все сектора' : 'Сектор ' + sector}`, 'success');
        }

        function showAllSectors() {
            const allButton = document.querySelector('.sector-btn');
            filterBySector(0, allButton);
        }

        function clearMarkers() {
            markers.forEach(marker => {
                map.removeLayer(marker);
            });
            markers = [];
        }

        function getColorForIntensity(intensity) {
            const colors = {
                'high': '#ff0000',    
                'medium': '#ffa500', 
                'low': '#008000'      
            };
            return colors[intensity] || '#808080'; 
        }

        function getIntensityText(intensity) {
            const texts = {
                'high': 'Высокая 🔴',
                'medium': 'Средняя 🟠', 
                'low': 'Низкая 🟢'
            };
            return texts[intensity] || 'Не определена';
        }

        async function uploadFile() {
            const fileInput = document.getElementById('fileUpload');
            const file = fileInput.files[0];
            const progress = document.getElementById('uploadProgress');
            const progressBar = document.querySelector('.progress-bar');
            const progressFill = document.querySelector('.progress-bar-fill');
            const progressText = document.querySelector('.progress-text');

            if (!file) {
                showNotification('Пожалуйста, выберите файл для загрузки', 'warning');
                return;
            }

            try {
                progress.style.display = 'block';
                progressBar.style.display = 'block';
                progressFill.style.width = '10%';
                progressText.textContent = 'Начало загрузки...';

                const formData = new FormData();
                formData.append('file', file);

                const response = await fetch('upload_handler.php', {
                    method: 'POST',
                    body: formData
                });

                progressFill.style.width = '50%';
                progressText.textContent = 'Обработка данных...';

                const result = await response.json();

                if (result.success) {
                    progressFill.style.width = '100%';
                    progressText.textContent = 'Завершено!';

                    showNotification(`✅ ${result.message}`, 'success');
                    closeModal('uploadModal');

                    fileInput.value = '';

                    setTimeout(() => {
                        loadStats();
                        loadMapData();
                    }, 2000);
                } else {
                    showNotification(`❌ ${result.message}`, 'error');
                }

            } catch (error) {
                showNotification('❌ Ошибка при загрузке файла: ' + error.message, 'error');
                console.error('Upload error:', error);
            } finally {
                setTimeout(() => {
                    progress.style.display = 'none';
                    progressBar.style.display = 'none';
                }, 2000);
            }
        }

        function showNotification(message, type = 'info') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification show ${type}`;

            setTimeout(() => {
                notification.classList.remove('show');
            }, 5000);
        }

        document.addEventListener('click', function (event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            console.log('Система медицинской аналитики СМП готова к работе');
        });

        function hideMapControls() {
            const sectorControls = document.querySelector('.sector-controls');
            const mapLegend = document.querySelector('.map-legend');
            const zoomControls = document.querySelector('.leaflet-control-zoom');

            if (sectorControls) sectorControls.style.display = 'none';
            if (mapLegend) mapLegend.style.display = 'none';
            if (zoomControls) zoomControls.style.display = 'none';
        }

        function showMapControls() {
            const sectorControls = document.querySelector('.sector-controls');
            const mapLegend = document.querySelector('.map-legend');
            const zoomControls = document.querySelector('.leaflet-control-zoom');

            if (sectorControls) sectorControls.style.display = 'block';
            if (mapLegend) mapLegend.style.display = 'block';
            if (zoomControls) zoomControls.style.display = 'block';
        }

        const originalOpenModal = openModal;
        const originalCloseModal = closeModal;

        openModal = function(modalId) {
            originalOpenModal(modalId);

            if (
                modalId === 'uploadModal' || 
                modalId === 'filtersModal' || 
                modalId === 'searchModal'
            ) {
                hideMapControls();
            }
        };

        closeModal = function(modalId) {
            originalCloseModal(modalId);

            if (
                modalId === 'uploadModal' || 
                modalId === 'filtersModal' || 
                modalId === 'searchModal'
            ) {
                showMapControls();
            }
        };
    </script>
</body>
</html>
