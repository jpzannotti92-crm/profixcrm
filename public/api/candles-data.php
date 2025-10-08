<?php
/**
 * Endpoint de datos OHLC históricos SIMULADOS
 * Elimina dependencia de proveedores externos
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Mapeo de símbolos para Forex
$forexSymbols = [
    'EURUSD' => 'EUR/USD',
    'GBPUSD' => 'GBP/USD', 
    'USDJPY' => 'USD/JPY',
    'USDCHF' => 'USD/CHF',
    'AUDUSD' => 'AUD/USD',
    'USDCAD' => 'USD/CAD',
    'NZDUSD' => 'NZD/USD',
    'EURGBP' => 'EUR/GBP',
    'EURJPY' => 'EUR/JPY',
    'GBPJPY' => 'GBP/JPY'
];

// Mapeo de timeframes (minutos)
$timeframes = [
    'M1' => 1,
    'M5' => 5,
    'M15' => 15,
    'M30' => 30,
    'H1' => 60,
    'H4' => 240,
    'D1' => 1440
];

/**
 * Generar datos OHLC simulados realistas
 */
function generateSimulatedOHLC($symbol, $timeframe, $from, $to) {
    global $timeframes;
    
    $timeframeMinutes = $timeframes[$timeframe] ?? 5;
    $fromTimestamp = strtotime($from);
    $toTimestamp = strtotime($to);
    
    // Precio base según el símbolo
    $basePrices = [
        'EURUSD' => 1.0850, 'GBPUSD' => 1.2650, 'USDJPY' => 149.25,
        'USDCHF' => 0.8750, 'AUDUSD' => 0.6750, 'USDCAD' => 1.3450,
        'NZDUSD' => 0.6150, 'EURGBP' => 0.8580, 'EURJPY' => 161.85, 'GBPJPY' => 188.75
    ];
    
    $basePrice = $basePrices[$symbol] ?? 1.0850;
    $results = [];
    
    // Generar velas cada X minutos
    for ($time = $fromTimestamp; $time < $toTimestamp; $time += ($timeframeMinutes * 60)) {
        // Simular movimiento realista
        $volatility = 0.0005; // 0.05% de volatilidad
        $change = (mt_rand(-100, 100) / 100) * $volatility;
        
        $open = $basePrice + $change;
        $high = $open + (mt_rand(0, 50) / 100000);
        $low = $open - (mt_rand(0, 50) / 100000);
        $close = $low + (($high - $low) * mt_rand(0, 100) / 100);
        
        $results[] = [
            'o' => round($open, 5),
            'h' => round($high, 5),
            'l' => round($low, 5),
            'c' => round($close, 5),
            'v' => mt_rand(100, 5000),
            't' => $time * 1000 // Timestamp en milliseconds
        ];
        
        $basePrice = $close; // Usar el close como base para la siguiente vela
    }
    
    return [
        'symbol' => $symbol,
        'totalResults' => count($results),
        'results' => $results
    ];
}

/**
 * Convertir datos simulados al formato WebTrader
 */
function convertToOHLC($data) {
    if (!isset($data['results']) || empty($data['results'])) {
        return [];
    }
    
    $candles = [];
    foreach ($data['results'] as $candle) {
        if (!isset($candle['o'], $candle['h'], $candle['l'], $candle['c'], $candle['t'])) {
            continue;
        }
        
        $candles[] = [
            'time' => (int)($candle['t'] / 1000),
            'open' => (float)$candle['o'],
            'high' => (float)$candle['h'],
            'low' => (float)$candle['l'],
            'close' => (float)$candle['c'],
            'volume' => isset($candle['v']) ? (int)$candle['v'] : 0
        ];
    }
    
    usort($candles, function($a, $b) { return $a['time'] - $b['time']; });
    return $candles;
}

// Procesar solicitudes
$endpoint = $_GET['endpoint'] ?? '';

switch ($endpoint) {
    case 'ohlc':
        $symbol = $_GET['symbol'] ?? 'EURUSD';
        $timeframe = $_GET['timeframe'] ?? 'M5';
        $days = (int)($_GET['days'] ?? 30);
        
        if (!isset($forexSymbols[$symbol])) {
            echo json_encode([
                'success' => false,
                'error' => 'Símbolo no soportado: ' . $symbol
            ]);
            exit;
        }
        
        $to = date('Y-m-d');
        $from = date('Y-m-d', strtotime("-{$days} days"));
        
        $simData = generateSimulatedOHLC($symbol, $timeframe, $from, $to);
        $candles = convertToOHLC($simData);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'symbol' => $symbol,
                'timeframe' => $timeframe,
                'candles' => $candles,
                'count' => count($candles),
                'from' => $from,
                'to' => $to,
                'source' => 'Simulated'
            ]
        ]);
        break;
    
    default:
        echo json_encode([
            'success' => false,
            'error' => 'Endpoint no válido. Disponibles: ohlc'
        ]);
        break;
}

?>