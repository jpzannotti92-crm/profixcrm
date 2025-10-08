<?php

namespace IaTradeCRM\Core;

class Response
{
    private $content;
    private $statusCode;
    private $headers;

    public function __construct($content = '', $statusCode = 200, $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Establece los datos de la respuesta
     */
    public function setData($data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Obtiene los datos de la respuesta
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Establece el código de estado HTTP
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Obtiene el código de estado HTTP
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Establece un header
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Obtiene un header
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Obtiene todos los headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Establece múltiples headers
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Envía la respuesta al cliente
     */
    public function send(): void
    {
        // Establecer código de estado HTTP
        http_response_code($this->statusCode);

        // Enviar headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Enviar contenido
        if ($this->data !== null) {
            if (is_string($this->data)) {
                echo $this->data;
            } else {
                echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        }
    }

    /**
     * Crea una respuesta de éxito
     */
    public static function success($data = null, string $message = 'Success', int $statusCode = 200): self
    {
        return new self([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Crea una respuesta de error
     */
    public static function error(string $message = 'Error', $errors = null, int $statusCode = 400): self
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return new self($response, $statusCode);
    }

    /**
     * Crea una respuesta de validación
     */
    public static function validation(array $errors, string $message = 'Validation failed'): self
    {
        return new self([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], 422);
    }

    /**
     * Crea una respuesta de no autorizado
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self([
            'success' => false,
            'message' => $message
        ], 401);
    }

    /**
     * Crea una respuesta de prohibido
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self([
            'success' => false,
            'message' => $message
        ], 403);
    }

    /**
     * Crea una respuesta de no encontrado
     */
    public static function notFound(string $message = 'Not found'): self
    {
        return new self([
            'success' => false,
            'message' => $message
        ], 404);
    }

    /**
     * Crea una respuesta de error interno del servidor
     */
    public static function serverError(string $message = 'Internal server error'): self
    {
        return new self([
            'success' => false,
            'message' => $message
        ], 500);
    }

    /**
     * Crea una respuesta de creado
     */
    public static function created($data = null, string $message = 'Created successfully'): self
    {
        return new self([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], 201);
    }

    /**
     * Crea una respuesta de actualizado
     */
    public static function updated($data = null, string $message = 'Updated successfully'): self
    {
        return new self([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], 200);
    }

    /**
     * Crea una respuesta de eliminado
     */
    public static function deleted(string $message = 'Deleted successfully'): self
    {
        return new self([
            'success' => true,
            'message' => $message
        ], 200);
    }

    /**
     * Crea una respuesta sin contenido
     */
    public static function noContent(): self
    {
        return new self(null, 204);
    }

    /**
     * Crea una respuesta de redirección
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self(null, $statusCode, [
            'Location' => $url
        ]);
    }

    /**
     * Crea una respuesta JSON personalizada
     */
    public static function json($data, int $statusCode = 200, array $headers = []): self
    {
        return new self($data, $statusCode, array_merge([
            'Content-Type' => 'application/json'
        ], $headers));
    }

    /**
     * Crea una respuesta HTML
     */
    public static function html(string $html, int $statusCode = 200, array $headers = []): self
    {
        return new self($html, $statusCode, array_merge([
            'Content-Type' => 'text/html'
        ], $headers));
    }

    /**
     * Crea una respuesta de texto plano
     */
    public static function text(string $text, int $statusCode = 200, array $headers = []): self
    {
        return new self($text, $statusCode, array_merge([
            'Content-Type' => 'text/plain'
        ], $headers));
    }

    /**
     * Crea una respuesta de archivo
     */
    public static function file(string $filePath, string $filename = null, array $headers = []): self
    {
        if (!file_exists($filePath)) {
            return self::notFound('File not found');
        }

        $filename = $filename ?: basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        $defaultHeaders = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => filesize($filePath)
        ];

        return new self(file_get_contents($filePath), 200, array_merge($defaultHeaders, $headers));
    }

    /**
     * Crea una respuesta de descarga
     */
    public static function download(string $filePath, string $filename = null): self
    {
        return self::file($filePath, $filename, [
            'Content-Disposition' => 'attachment; filename="' . ($filename ?: basename($filePath)) . '"'
        ]);
    }

    /**
     * Crea una respuesta paginada
     */
    public static function paginated(array $data, int $total, int $page, int $perPage, string $message = 'Data retrieved successfully'): self
    {
        $totalPages = ceil($total / $perPage);
        
        return new self([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]);
    }

    /**
     * Convierte la respuesta a string
     */
    public function __toString(): string
    {
        if (is_string($this->data)) {
            return $this->data;
        }
        
        return json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}