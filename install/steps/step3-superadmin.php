<?php
require_once '../classes/InstallationWizard.php';

$wizard = new InstallationWizard();
$wizard->requireStep(2); // Requiere que el paso 2 esté completado

$errors = [];
$success = false;

// Procesar formulario
if ($_POST && $wizard->validateCSRF($_POST['csrf_token'] ?? '')) {
    $userData = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? '')
    ];
    
    // Validaciones
    if (empty($userData['username'])) {
        $errors[] = 'El nombre de usuario es requerido';
    } elseif (strlen($userData['username']) < 3) {
        $errors[] = 'El nombre de usuario debe tener al menos 3 caracteres';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $userData['username'])) {
        $errors[] = 'El nombre de usuario solo puede contener letras, números y guiones bajos';
    }
    
    if (empty($userData['email'])) {
        $errors[] = 'El email es requerido';
    } elseif (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El formato del email no es válido';
    }
    
    if (empty($userData['password'])) {
        $errors[] = 'La contraseña es requerida';
    } elseif (strlen($userData['password']) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $userData['password'])) {
        $errors[] = 'La contraseña debe contener al menos una letra minúscula, una mayúscula y un número';
    }
    
    if ($userData['password'] !== $userData['confirm_password']) {
        $errors[] = 'Las contraseñas no coinciden';
    }
    
    if (empty($userData['first_name'])) {
        $errors[] = 'El nombre es requerido';
    }
    
    if (empty($userData['last_name'])) {
        $errors[] = 'El apellido es requerido';
    }
    
    if (empty($errors)) {
        try {
            // Obtener configuración de base de datos
            $dbConfig = $wizard->getConfig('database');
            if (!$dbConfig) {
                throw new Exception('Configuración de base de datos no encontrada');
            }
            
            // Conectar a la base de datos
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // Verificar si ya existe un superadmin
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM users u 
                JOIN user_roles ur ON u.id = ur.user_id 
                JOIN roles r ON ur.role_id = r.id 
                WHERE r.name = 'super_admin'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                $errors[] = 'Ya existe un usuario Super Administrador en el sistema';
            } else {
                // Verificar si el username o email ya existen
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$userData['username'], $userData['email']]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    $errors[] = 'El nombre de usuario o email ya están en uso';
                } else {
                    // Crear el usuario
                    $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
                    
                    $pdo->beginTransaction();
                    
                    try {
                        // Insertar usuario
                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, email, password_hash, first_name, last_name, phone, status, email_verified, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, 'active', 1, NOW())
                        ");
                        $stmt->execute([
                            $userData['username'],
                            $userData['email'],
                            $passwordHash,
                            $userData['first_name'],
                            $userData['last_name'],
                            $userData['phone'] ?: null
                        ]);
                        
                        $userId = $pdo->lastInsertId();
                        
                        // Obtener el rol de super_admin
                        $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'super_admin'");
                        $stmt->execute();
                        $role = $stmt->fetch();
                        
                        if (!$role) {
                            throw new Exception('Rol de Super Administrador no encontrado');
                        }
                        
                        // Asignar rol al usuario
                        $stmt = $pdo->prepare("
                            INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at) 
                            VALUES (?, ?, ?, NOW())
                        ");
                        $stmt->execute([$userId, $role['id'], $userId]);
                        
                        $pdo->commit();
                        
                        // Guardar información del superadmin
                        $wizard->setConfig('superadmin', [
                            'id' => $userId,
                            'username' => $userData['username'],
                            'email' => $userData['email'],
                            'first_name' => $userData['first_name'],
                            'last_name' => $userData['last_name']
                        ]);
                        
                        $wizard->completeStep(3);
                        $wizard->setMessage('success', 'Usuario Super Administrador creado exitosamente');
                        $success = true;
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                }
            }
            
        } catch (Exception $e) {
            $errors[] = 'Error al crear el usuario: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $wizard->setMessage('error', 'Por favor corrige los siguientes errores:');
        $wizard->setMessage('error_details', $errors);
    }
}

// Obtener configuración guardada
$savedData = $wizard->getConfig('superadmin') ?? [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Super Administrador - IATrade CRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    <i class="fas fa-user-shield text-blue-600 mr-3"></i>
                    Crear Super Administrador
                </h1>
                <p class="text-gray-600">Paso 3 de 5: Crea el usuario principal del sistema</p>
            </div>

            <!-- Progress Bar -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-600">Progreso de instalación</span>
                    <span class="text-sm text-gray-600">60%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: 60%"></div>
                </div>
            </div>

            <!-- Messages -->
            <?php echo $wizard->renderMessages(); ?>

            <!-- Main Content -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <?php if ($success): ?>
                    <!-- Success State -->
                    <div class="text-center py-8">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                            <i class="fas fa-check text-green-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">¡Super Administrador Creado!</h3>
                        <p class="text-gray-600 mb-6">
                            El usuario <strong><?php echo htmlspecialchars($savedData['username'] ?? ''); ?></strong> 
                            ha sido creado exitosamente con permisos de Super Administrador.
                        </p>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-left">
                            <h4 class="font-medium text-blue-900 mb-2">Información del usuario:</h4>
                            <ul class="text-sm text-blue-800 space-y-1">
                                <li><strong>Usuario:</strong> <?php echo htmlspecialchars($savedData['username'] ?? ''); ?></li>
                                <li><strong>Email:</strong> <?php echo htmlspecialchars($savedData['email'] ?? ''); ?></li>
                                <li><strong>Nombre:</strong> <?php echo htmlspecialchars(($savedData['first_name'] ?? '') . ' ' . ($savedData['last_name'] ?? '')); ?></li>
                                <li><strong>Rol:</strong> Super Administrador</li>
                            </ul>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Form -->
                    <form method="POST" class="space-y-6" x-data="{ 
                        showPassword: false,
                        showConfirmPassword: false,
                        password: '',
                        confirmPassword: '',
                        passwordStrength: 0,
                        
                        checkPasswordStrength() {
                            let strength = 0;
                            if (this.password.length >= 8) strength++;
                            if (/[a-z]/.test(this.password)) strength++;
                            if (/[A-Z]/.test(this.password)) strength++;
                            if (/\d/.test(this.password)) strength++;
                            if (/[^A-Za-z0-9]/.test(this.password)) strength++;
                            this.passwordStrength = strength;
                        },
                        
                        getStrengthColor() {
                            if (this.passwordStrength <= 2) return 'bg-red-500';
                            if (this.passwordStrength <= 3) return 'bg-yellow-500';
                            return 'bg-green-500';
                        },
                        
                        getStrengthText() {
                            if (this.passwordStrength <= 2) return 'Débil';
                            if (this.passwordStrength <= 3) return 'Media';
                            return 'Fuerte';
                        }
                    }">
                        <input type="hidden" name="csrf_token" value="<?php echo $wizard->getCSRFToken(); ?>">
                        
                        <!-- Info Box -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                                <div class="text-sm text-blue-800">
                                    <p class="font-medium mb-1">Información importante:</p>
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>Este será el usuario principal con acceso completo al sistema</li>
                                        <li>Podrás crear más usuarios y administradores después</li>
                                        <li>Guarda estas credenciales en un lugar seguro</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- User Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-user text-gray-400 mr-2"></i>
                                    Nombre de Usuario *
                                </label>
                                <input type="text" 
                                       id="username" 
                                       name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="admin"
                                       pattern="[a-zA-Z0-9_]+"
                                       required>
                                <p class="text-xs text-gray-500 mt-1">Solo letras, números y guiones bajos. Mínimo 3 caracteres.</p>
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-envelope text-gray-400 mr-2"></i>
                                    Email *
                                </label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="admin@empresa.com"
                                       required>
                                <p class="text-xs text-gray-500 mt-1">Email válido para notificaciones del sistema.</p>
                            </div>

                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-id-card text-gray-400 mr-2"></i>
                                    Nombre *
                                </label>
                                <input type="text" 
                                       id="first_name" 
                                       name="first_name" 
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Juan"
                                       required>
                            </div>

                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-id-card text-gray-400 mr-2"></i>
                                    Apellido *
                                </label>
                                <input type="text" 
                                       id="last_name" 
                                       name="last_name" 
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Pérez"
                                       required>
                            </div>

                            <div class="md:col-span-2">
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-phone text-gray-400 mr-2"></i>
                                    Teléfono (Opcional)
                                </label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="+1234567890">
                            </div>
                        </div>

                        <!-- Password Section -->
                        <div class="border-t pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                <i class="fas fa-lock text-blue-600 mr-2"></i>
                                Configurar Contraseña
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                        Contraseña *
                                    </label>
                                    <div class="relative">
                                        <input :type="showPassword ? 'text' : 'password'" 
                                               id="password" 
                                               name="password" 
                                               x-model="password"
                                               @input="checkPasswordStrength()"
                                               class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                               placeholder="Contraseña segura"
                                               required>
                                        <button type="button" 
                                                @click="showPassword = !showPassword"
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'" class="text-gray-400"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Password Strength -->
                                    <div class="mt-2" x-show="password.length > 0">
                                        <div class="flex items-center space-x-2">
                                            <div class="flex-1 bg-gray-200 rounded-full h-2">
                                                <div :class="getStrengthColor()" 
                                                     class="h-2 rounded-full transition-all duration-300"
                                                     :style="`width: ${(passwordStrength / 5) * 100}%`"></div>
                                            </div>
                                            <span class="text-xs font-medium" 
                                                  :class="passwordStrength <= 2 ? 'text-red-600' : passwordStrength <= 3 ? 'text-yellow-600' : 'text-green-600'"
                                                  x-text="getStrengthText()"></span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                        Confirmar Contraseña *
                                    </label>
                                    <div class="relative">
                                        <input :type="showConfirmPassword ? 'text' : 'password'" 
                                               id="confirm_password" 
                                               name="confirm_password" 
                                               x-model="confirmPassword"
                                               class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                               placeholder="Repetir contraseña"
                                               required>
                                        <button type="button" 
                                                @click="showConfirmPassword = !showConfirmPassword"
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <i :class="showConfirmPassword ? 'fas fa-eye-slash' : 'fas fa-eye'" class="text-gray-400"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Password Match -->
                                    <div class="mt-2" x-show="confirmPassword.length > 0">
                                        <div class="flex items-center space-x-2">
                                            <i :class="password === confirmPassword ? 'fas fa-check text-green-600' : 'fas fa-times text-red-600'"></i>
                                            <span class="text-xs" 
                                                  :class="password === confirmPassword ? 'text-green-600' : 'text-red-600'"
                                                  x-text="password === confirmPassword ? 'Las contraseñas coinciden' : 'Las contraseñas no coinciden'"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Password Requirements -->
                            <div class="mt-4 bg-gray-50 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Requisitos de contraseña:</h4>
                                <ul class="text-xs text-gray-600 space-y-1">
                                    <li class="flex items-center">
                                        <i :class="password.length >= 8 ? 'fas fa-check text-green-600' : 'fas fa-times text-red-600'" class="mr-2"></i>
                                        Al menos 8 caracteres
                                    </li>
                                    <li class="flex items-center">
                                        <i :class="/[a-z]/.test(password) ? 'fas fa-check text-green-600' : 'fas fa-times text-red-600'" class="mr-2"></i>
                                        Una letra minúscula
                                    </li>
                                    <li class="flex items-center">
                                        <i :class="/[A-Z]/.test(password) ? 'fas fa-check text-green-600' : 'fas fa-times text-red-600'" class="mr-2"></i>
                                        Una letra mayúscula
                                    </li>
                                    <li class="flex items-center">
                                        <i :class="/\d/.test(password) ? 'fas fa-check text-green-600' : 'fas fa-times text-red-600'" class="mr-2"></i>
                                        Un número
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="border-t pt-6">
                            <button type="submit" 
                                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-user-plus mr-2"></i>
                                Crear Super Administrador
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Navigation -->
            <div class="flex justify-between mt-8">
                <a href="../index.php?step=2" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Anterior
                </a>
                
                <?php if ($success): ?>
                    <a href="../index.php?step=4" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        Siguiente
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                <?php else: ?>
                    <button type="button" 
                            disabled
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-400 cursor-not-allowed">
                        Siguiente
                        <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>