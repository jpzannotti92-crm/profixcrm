<?php
/**
 * Generar archivo CSV con 500 leads de prueba para probar el importador
 */

$nombres = [
    'Juan', 'María', 'Carlos', 'Ana', 'Luis', 'Laura', 'Pedro', 'Sofía', 'Diego', 'Valentina',
    'Miguel', 'Camila', 'José', 'Isabella', 'Antonio', 'Gabriela', 'Manuel', 'Andrea', 'Francisco', 'Daniela',
    'David', 'Lucía', 'Jorge', 'Emma', 'Alberto', 'Martina', 'Ricardo', 'Paula', 'Roberto', 'Alejandra',
    'Fernando', 'Mariana', 'Sergio', 'Julia', 'Eduardo', 'Clara', 'Óscar', 'Renata', 'Jaime', 'Natalia',
    'Raúl', 'Valeria', 'Hugo', 'Bianca', 'Pablo', 'Florencia', 'Ernesto', 'Carolina', 'César', 'Pilar'
];

$apellidos = [
    'García', 'Rodríguez', 'González', 'Fernández', 'López', 'Martínez', 'Sánchez', 'Pérez', 'Gómez', 'Martín',
    'Jiménez', 'Ruiz', 'Hernández', 'Díaz', 'Moreno', 'Álvarez', 'Romero', 'Alonso', 'Gutiérrez', 'Navarro',
    'Torres', 'Domínguez', 'Vázquez', 'Ramos', 'Gil', 'Ramírez', 'Serrano', 'Blanco', 'Molina', 'Morales',
    'Suárez', 'Ortega', 'Delgado', 'Castro', 'Ortiz', 'Rubio', 'Marín', 'Santos', 'Iglesias', 'Cruz',
    'Medina', 'Muñoz', 'Caballero', 'Núñez', 'Peña', 'Rojas', 'León', 'Vega', 'Méndez', 'Herrera'
];

$dominios = [
    'gmail.com', 'hotmail.com', 'yahoo.com', 'outlook.com', 'empresa.com', 'corporation.com', 'business.net',
    'consulting.com', 'services.com', 'solutions.com', 'tech.com', 'digital.com', 'group.com', 'inc.com'
];

$empresas = [
    'Tech Corp', 'Global Solutions', 'Innovation Inc', 'Digital Services', 'Consulting Group', 'Business Partners',
    'Trading Company', 'Investment Firm', 'Marketing Agency', 'Software House', 'Data Analytics', 'Cloud Systems',
    'Financial Services', 'International Trade', 'E-commerce Solutions', 'Mobile Development', 'Web Services',
    'IT Consulting', 'Business Intelligence', 'Customer Success', 'Sales Force', 'Product Development',
    'Research & Development', 'Quality Assurance', 'Project Management', 'Operations Center', 'Logistics Hub',
    'Supply Chain', 'Manufacturing Co', 'Distribution Network', 'Retail Solutions', 'Customer Support',
    'Human Resources', 'Finance Department', 'Legal Services', 'Marketing Team', 'Sales Department',
    'Operations Team', 'Management Office', 'Executive Suite', 'Board of Directors', 'Shareholder Services'
];

$puestos = [
    'CEO', 'CTO', 'CFO', 'CMO', 'COO', 'Director', 'Manager', 'Supervisor', 'Team Lead', 'Senior Developer',
    'Junior Developer', 'Analyst', 'Consultant', 'Specialist', 'Coordinator', 'Administrator', 'Assistant',
    'Representative', 'Executive', 'Officer', 'Engineer', 'Architect', 'Designer', 'Planner', 'Strategist',
    'Researcher', 'Scientist', 'Technician', 'Support Specialist', 'Customer Success Manager', 'Sales Executive',
    'Marketing Manager', 'Product Manager', 'Project Manager', 'Operations Manager', 'Finance Manager',
    'HR Manager', 'Legal Counsel', 'Business Analyst', 'Data Analyst', 'Quality Assurance Manager',
    'Business Development', 'Account Manager', 'Client Relations', 'Partnership Manager', 'Vendor Manager'
];

$paises = ['México', 'España', 'Colombia', 'Argentina', 'Perú', 'Chile', 'Ecuador', 'Venezuela', 'Guatemala', 'Cuba'];

echo "=== GENERANDO ARCHIVO CSV CON 500 LEADS ===\n";

$filename = 'leads_500_prueba.csv';
$handle = fopen($filename, 'w');

// Escribir encabezados
fputcsv($handle, ['Nombre', 'Apellido', 'Email', 'Telefono', 'Pais', 'Empresa', 'Puesto', 'Fuente']);

// Generar 500 leads
for ($i = 1; $i <= 500; $i++) {
    $nombre = $nombres[array_rand($nombres)];
    $apellido = $apellidos[array_rand($apellidos)];
    $empresa = $empresas[array_rand($empresas)];
    $puesto = $puestos[array_rand($puestos)];
    $pais = $paises[array_rand($paises)];
    $dominio = $dominios[array_rand($dominios)];
    
    // Crear email válido
    $email = strtolower($nombre . '.' . $apellido . rand(1, 999) . '@' . $dominio);
    
    // Crear teléfono
    $telefono = '+' . rand(1, 99) . '-' . rand(100, 999) . '-' . rand(100, 9999) . '-' . rand(1000, 9999);
    
    // Fuente aleatoria
    $fuentes = ['Google Ads', 'Facebook', 'LinkedIn', 'Referido', 'Webinar', 'Evento', 'Email Marketing', 'SEO'];
    $fuente = $fuentes[array_rand($fuentes)];
    
    fputcsv($handle, [$nombre, $apellido, $email, $telefono, $pais, $empresa, $puesto, $fuente]);
    
    if ($i % 50 == 0) {
        echo "✅ Generados $i leads...\n";
    }
}

fclose($handle);

echo "\n🎉 ARCHIVO GENERADO EXITOSAMENTE!\n";
echo "📁 Nombre: $filename\n";
echo "📊 Tamaño: " . number_format(filesize($filename)) . " bytes\n";
echo "📋 Líneas: " . (count(file($filename)) - 1) . " leads\n";

// Mostrar muestra de los primeros 5 leads
echo "\n📋 MUESTRA DE LOS PRIMEROS 5 LEADS:\n";
$lines = file($filename);
for ($i = 1; $i <= min(5, count($lines) - 1); $i++) {
    $data = str_getcsv($lines[$i]);
    echo "   $i. {$data[0]} {$data[1]} - {$data[2]}\n";
}