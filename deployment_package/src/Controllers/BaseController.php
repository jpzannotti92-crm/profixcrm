<?php

namespace IaTradeCRM\Controllers;

use IaTradeCRM\Core\Request;
use IaTradeCRM\Core\Response;

abstract class BaseController
{
    protected $request;

    public function __construct()
    {
        $this->request = new Request();
    }

    /**
     * Respuesta JSON exitosa
     */
    protected function success($data = null, $message = 'Success', $statusCode = 200)
    {
        return Response::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Respuesta JSON de error
     */
    protected function error($message = 'Error', $data = null, $statusCode = 400)
    {
        return Response::json([
            'success' => false,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Respuesta de validación
     */
    protected function validationError($errors, $message = 'Validation failed')
    {
        return Response::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], 422);
    }

    /**
     * Respuesta no autorizada
     */
    protected function unauthorized($message = 'No autorizado')
    {
        return Response::json([
            'success' => false,
            'message' => $message
        ], 401);
    }

    /**
     * Respuesta prohibida
     */
    protected function forbidden($message = 'Acceso prohibido')
    {
        return Response::json([
            'success' => false,
            'message' => $message
        ], 403);
    }

    /**
     * Respuesta no encontrada
     */
    protected function notFound($message = 'Recurso no encontrado')
    {
        return Response::json([
            'success' => false,
            'message' => $message
        ], 404);
    }

    /**
     * Validar datos de entrada
     */
    protected function validate($rules)
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $this->request->get($field);
            
            if ($rule === 'required' && empty($value)) {
                $errors[$field] = "El campo {$field} es requerido";
            }
            
            if (strpos($rule, 'email') !== false && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "El campo {$field} debe ser un email válido";
            }
            
            if (strpos($rule, 'min:') !== false && !empty($value)) {
                $min = (int) str_replace('min:', '', $rule);
                if (strlen($value) < $min) {
                    $errors[$field] = "El campo {$field} debe tener al menos {$min} caracteres";
                }
            }
        }
        
        return $errors;
    }
}