<?php

namespace IaTradeCRM\Controllers;

use IaTradeCRM\Models\Lead;
use IaTradeCRM\Models\User;
use IaTradeCRM\Models\Desk;
use IaTradeCRM\Core\Response;

/**
 * Controlador del Dashboard
 */
class DashboardController extends BaseController
{
    /**
     * Obtiene los datos del dashboard
     */
    public function index(): Response
    {
        if (!$this->requireAuth()) {
            return $this->unauthorized();
        }

        try {
            $data = [
                'kpis' => $this->getKpis(),
                'charts' => $this->getChartData(),
                'recent_leads' => $this->getRecentLeads(),
                'alerts' => $this->getAlerts()
            ];

            return $this->success($data, 'Dashboard data loaded successfully');
        } catch (\Exception $e) {
            return $this->error('Error loading dashboard data: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Obtiene los KPIs principales
     */
    private function getKpis(): array
    {
        // Simular datos de KPIs
        return [
            'total_leads' => [
                'value' => 1247,
                'change' => 12.5,
                'trend' => 'up'
            ],
            'ftd_conversions' => [
                'value' => 186,
                'change' => 8.2,
                'trend' => 'up'
            ],
            'total_revenue' => [
                'value' => 47250,
                'change' => 15.3,
                'trend' => 'up'
            ],
            'conversion_rate' => [
                'value' => 14.9,
                'change' => -2.1,
                'trend' => 'down'
            ]
        ];
    }

    /**
     * Obtiene datos para gráficos
     */
    private function getChartData(): array
    {
        return [
            'conversions' => [
                'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
                'data' => [65, 78, 90, 81, 95, 105]
            ],
            'sources' => [
                'labels' => ['Google Ads', 'Facebook', 'Orgánico', 'Referidos'],
                'data' => [35, 25, 25, 15]
            ]
        ];
    }

    /**
     * Obtiene leads recientes
     */
    private function getRecentLeads(): array
    {
        // Simular datos de leads recientes
        return [
            [
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@email.com',
                'phone' => '+1 234 567 8900',
                'status' => 'new',
                'priority' => 'high',
                'assigned_to' => 'María García',
                'created_at' => '2024-01-15'
            ],
            [
                'id' => 2,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@email.com',
                'phone' => '+1 234 567 8901',
                'status' => 'contacted',
                'priority' => 'medium',
                'assigned_to' => 'Carlos López',
                'created_at' => '2024-01-15'
            ],
            [
                'id' => 3,
                'first_name' => 'Mike',
                'last_name' => 'Johnson',
                'email' => 'mike.johnson@email.com',
                'phone' => '+1 234 567 8902',
                'status' => 'interested',
                'priority' => 'high',
                'assigned_to' => 'Ana Martínez',
                'created_at' => '2024-01-14'
            ]
        ];
    }

    /**
     * Obtiene alertas del sistema
     */
    private function getAlerts(): array
    {
        return [
            [
                'id' => 1,
                'type' => 'warning',
                'title' => 'Leads sin contactar',
                'message' => 'Tienes 15 leads nuevos sin contactar',
                'created_at' => '2024-01-15 10:30:00'
            ],
            [
                'id' => 2,
                'type' => 'info',
                'title' => 'Meta mensual',
                'message' => 'Has alcanzado el 75% de tu meta mensual',
                'created_at' => '2024-01-15 09:15:00'
            ],
            [
                'id' => 3,
                'type' => 'success',
                'title' => 'Nueva conversión',
                'message' => 'Lead convertido a FTD por $500',
                'created_at' => '2024-01-15 08:45:00'
            ]
        ];
    }
}