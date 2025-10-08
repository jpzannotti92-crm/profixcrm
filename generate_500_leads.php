<?php
// Generar 500 leads para prueba masiva
header('Content-Type: text/plain');

$nombres = [
    'Juan', 'María', 'Carlos', 'Ana', 'Luis', 'Sofía', 'Diego', 'Laura', 'Miguel', 'Elena',
    'Antonio', 'Lucía', 'Manuel', 'Martina', 'Francisco', 'Valentina', 'David', 'Camila', 'José', 'Daniela',
    'Pablo', 'Julia', 'Fernando', 'Clara', 'Jorge', 'Sara', 'Ricardo', 'Alba', 'Roberto', 'Noa',
    'Alberto', 'Carmen', 'Álvaro', 'Paula', 'Enrique', 'Sandra', 'Rafael', 'Natalia', 'Pedro', 'Eva',
    'Santiago', 'Cristina', 'Víctor', 'Beatriz', 'Ramón', 'Rosa', 'Emilio', 'Teresa', 'Jaime', 'Silvia'
];

$apellidos = [
    'García', 'Rodríguez', 'González', 'Fernández', 'López', 'Martínez', 'Sánchez', 'Pérez', 'Gómez', 'Martín',
    'Jiménez', 'Ruiz', 'Hernández', 'Díaz', 'Álvarez', 'Moreno', 'Muñoz', 'Romero', 'Alonso', 'Gutiérrez',
    'Navarro', 'Torres', 'Domínguez', 'Gil', 'Vázquez', 'Serrano', 'Blanco', 'Molina', 'Morales', 'Suárez',
    'Ortega', 'Delgado', 'Castro', 'Ortiz', 'Rubio', 'Marín', 'Sanz', 'Iglesias', 'Medina', 'Garrido',
    'Cortés', 'Castillo', 'Lozano', 'Guerrero', 'Cano', 'Prieto', 'Méndez', 'Cruz', 'Calvo', 'Gallego'
];

$empresas = [
    'Tech Solutions S.L.', 'Digital Marketing Co.', 'Global Consulting Group', 'Innovation Labs', 'Smart Systems Inc.',
    'Creative Studios', 'Business Analytics Pro', 'Cloud Services Ltd.', 'Data Intelligence Corp', 'Web Development Hub',
    'Mobile Apps Solutions', 'E-commerce Experts', 'Social Media Agency', 'SEO Masters', 'Content Creators S.A.',
    'Brand Strategy Co.', 'Market Research Pro', 'Customer Success Inc.', 'Sales Force Solutions', 'Lead Generation Experts',
    'Digital Transformation', 'AI & Machine Learning', 'Blockchain Technologies', 'Cybersecurity Services', 'FinTech Solutions',
    'HealthTech Innovations', 'EdTech Platforms', 'Real Estate Tech', 'Travel Tech Solutions', 'FoodTech Startups'
];

$paises = ['España', 'México', 'Argentina', 'Colombia', 'Chile', 'Perú', 'Venezuela', 'Ecuador', 'Uruguay', 'Bolivia'];
$ciudades = ['Madrid', 'Barcelona', 'Valencia', 'Sevilla', 'Bilbao', 'Ciudad de México', 'Guadalajara', 'Monterrey', 'Buenos Aires', 'Córdoba'];
$sources = ['Facebook', 'Google', 'LinkedIn', 'Referral', 'Website', 'Email Campaign', 'Cold Call', 'Social Media', 'Ads', 'Organic'];
$campaigns = ['Campaña Verano 2024', 'Black Friday 2024', 'Navidad 2024', 'Año Nuevo 2025', 'Campaña Primavera', 'Q1 2024', 'Q2 2024', 'Q3 2024', 'Q4 2024', 'Holiday Campaign'];

$leads = [];

echo "Generando 500 leads...\n\n";

for ($i = 1; $i <= 500; $i++) {
    $nombre = $nombres[array_rand($nombres)];
    $apellido = $apellidos[array_rand($apellidos)];
    $empresa = $empresas[array_rand($empresas)];
    $pais = $paises[array_rand($paises)];
    $ciudad = $ciudades[array_rand($ciudades)];
    $source = $sources[array_rand($sources)];
    $campaign = $campaigns[array_rand($campaigns)];
    
    // Generar email único
    $email = strtolower($nombre . '.' . $apellido . $i . '@' . str_replace(' ', '', strtolower($empresa)) . '.com');
    
    // Generar teléfono
    $telefono = '+34' . rand(600000000, 999999999);
    
    // Generar presupuesto aleatorio
    $budget = rand(10000, 100000);
    
    $lead = [
        'first_name' => $nombre,
        'last_name' => $apellido,
        'email' => $email,
        'phone' => $telefono,
        'country' => $pais,
        'city' => $ciudad,
        'company' => $empresa,
        'job_title' => 'Manager',
        'source' => $source,
        'campaign' => $campaign,
        'status' => 'new',
        'priority' => 'medium',
        'value' => $budget,
        'notes' => 'Lead generado automáticamente para prueba masiva'
    ];
    
    $leads[] = $lead;
    
    echo "Lead $i: $nombre $apellido - $email - $empresa - $budget€\n";
}

echo "\n=== TOTAL GENERADOS: " . count($leads) . " LEADS ===\n";

// Guardar en archivo JSON
$jsonFile = '500_leads.json';
file_put_contents($jsonFile, json_encode($leads, JSON_PRETTY_PRINT));
echo "\nArchivo JSON creado: $jsonFile\n";

// Crear CSV también
$csvFile = '500_leads.csv';
$csvContent = "first_name,last_name,email,phone,country,city,company,job_title,source,campaign,status,priority,value,notes\n";

foreach ($leads as $lead) {
    $csvContent .= implode(',', [
        $lead['first_name'],
        $lead['last_name'],
        $lead['email'],
        $lead['phone'],
        $lead['country'],
        $lead['city'],
        $lead['company'],
        $lead['job_title'],
        $lead['source'],
        $lead['campaign'],
        $lead['status'],
        $lead['priority'],
        $lead['value'],
        $lead['notes']
    ]) . "\n";
}

file_put_contents($csvFile, $csvContent);
echo "Archivo CSV creado: $csvFile\n";

echo "\n¡Generación completada!\n";
?>