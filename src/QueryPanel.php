<?php
namespace KnifeLemon\EasyQuery;

use Tracy\IBarPanel;

/**
 * QueryPanel - Custom Tracy Bar Panel for EasyQuery
 * 
 * Displays query building information in Tracy debug bar
 */
class QueryPanel implements IBarPanel {
    /**
     * Renders HTML code for custom tab
     */
    public function getTab() : string {
        if (!QueryLogger::isEnabled()) {
            return '';
        }

        $metrics = QueryLogger::getMetrics();
        $total = $metrics['total_queries'];

        return '
        <span title="EasyQuery">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="16" height="16" rx="2" fill="#4CAF50"/>
                <path d="M3 5h10M3 8h10M3 11h10" stroke="white" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span class="tracy-label">SQL: ' . $total . '</span>
        </span>';
    }

    /**
     * Renders HTML code for custom panel
     */
    public function getPanel() : string {
        if (!QueryLogger::isEnabled()) {
            return '<h1>EasyQuery</h1><p>Logger not enabled</p>';
        }

        $queries = QueryLogger::getQueries();
        $metrics = QueryLogger::getMetrics();

        $html = $this->renderStyles();
        $html .= '<h1>EasyQuery - SQL Builder</h1>';
        $html .= '<div class="gq-panel">';

        // Summary Cards
        $html .= $this->renderSummaryCards($metrics);

        // Queries List
        $html .= '<h2>Queries</h2>';
        $html .= $this->renderQueriesList($queries);

        $html .= '</div>';
        $html .= $this->renderScripts();

        return $html;
    }

    /**
     * Render custom styles
     */
    private function renderStyles() : string {
        return <<<'HTML'
        <style>
            .gq-panel {
                min-width: 600px;
                max-width: 1000px;
                padding: 10px;
            }
            .gq-summary {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 10px;
                margin-bottom: 20px;
            }
            .gq-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 15px;
                border-radius: 8px;
                text-align: center;
            }
            .gq-card-value {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .gq-card-label {
                font-size: 12px;
                opacity: 0.9;
            }
            .gq-query {
                background: #f5f5f5;
                border-left: 4px solid #4CAF50;
                padding: 15px;
                margin-bottom: 15px;
                border-radius: 4px;
            }
            .gq-query-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            .gq-query-action {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-weight: bold;
                font-size: 11px;
                text-transform: uppercase;
            }
            .gq-action-select { background: #2196F3; color: white; }
            .gq-action-insert { background: #4CAF50; color: white; }
            .gq-action-update { background: #FF9800; color: white; }
            .gq-action-delete { background: #f44336; color: white; }
            .gq-action-count { background: #9C27B0; color: white; }
            .gq-query-time {
                font-size: 11px;
                color: #666;
            }
            .gq-sql {
                background: #263238;
                color: #aed581;
                padding: 10px;
                border-radius: 4px;
                font-family: 'Consolas', 'Monaco', monospace;
                font-size: 12px;
                overflow-x: auto;
                margin-bottom: 10px;
            }
            .gq-params {
                display: flex;
                gap: 5px;
                flex-wrap: wrap;
            }
            .gq-param {
                background: #e3f2fd;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-family: 'Consolas', 'Monaco', monospace;
            }
            .gq-details {
                margin-top: 10px;
                padding: 10px;
                background: white;
                border-radius: 4px;
                border: 1px solid #e0e0e0;
                display: none;
            }
            .gq-details.active {
                display: block;
            }
            .gq-details-row {
                display: flex;
                padding: 5px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            .gq-details-label {
                font-weight: bold;
                width: 120px;
                color: #666;
            }
            .gq-details-value {
                flex: 1;
                font-family: 'Consolas', 'Monaco', monospace;
                font-size: 11px;
            }
            .gq-toggle {
                background: none;
                border: none;
                color: #2196F3;
                cursor: pointer;
                font-size: 11px;
                text-decoration: underline;
            }
        </style>
        HTML;
    }

    /**
     * Render summary cards
     */
    private function renderSummaryCards(array $metrics) : string {
        $html = '<div class="gq-summary">';

        $html .= '<div class="gq-card">';
        $html .= '<div class="gq-card-value">' . $metrics['total_queries'] . '</div>';
        $html .= '<div class="gq-card-label">Total Queries</div>';
        $html .= '</div>';

        $html .= '<div class="gq-card">';
        $html .= '<div class="gq-card-value">' . $metrics['select_queries'] . '</div>';
        $html .= '<div class="gq-card-label">SELECT</div>';
        $html .= '</div>';

        $html .= '<div class="gq-card">';
        $html .= '<div class="gq-card-value">' . $metrics['insert_queries'] . '</div>';
        $html .= '<div class="gq-card-label">INSERT</div>';
        $html .= '</div>';

        $html .= '<div class="gq-card">';
        $html .= '<div class="gq-card-value">' . $metrics['update_queries'] . '</div>';
        $html .= '<div class="gq-card-label">UPDATE</div>';
        $html .= '</div>';

        $html .= '<div class="gq-card">';
        $html .= '<div class="gq-card-value">' . $metrics['delete_queries'] . '</div>';
        $html .= '<div class="gq-card-label">DELETE</div>';
        $html .= '</div>';

        $html .= '<div class="gq-card">';
        $html .= '<div class="gq-card-value">' . $metrics['count_queries'] . '</div>';
        $html .= '<div class="gq-card-label">COUNT</div>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render queries list
     */
    private function renderQueriesList(array $queries) : string {
        $html = '';

        foreach ($queries as $query) {
            $action = $query['action'];
            $html .= '<div class="gq-query">';
            $html .= '<div class="gq-query-header">';
            $html .= '<span class="gq-query-action gq-action-' . $action . '">' . $action . '</span>';
            $html .= '<span class="gq-query-time">#' . $query['id'] . ' • +' . round($query['timestamp'] * 1000, 2) . 'ms</span>';
            $html .= '</div>';

            // SQL
            $html .= '<div class="gq-sql">' . htmlspecialchars($query['output']['sql']) . '</div>';

            // Params
            if (!empty($query['output']['params'])) {
                $html .= '<div style="margin-bottom: 10px;"><strong style="font-size: 11px;">Parameters:</strong></div>';
                $html .= '<div class="gq-params">';
                foreach ($query['output']['params'] as $param) {
                    $html .= '<span class="gq-param">' . htmlspecialchars(var_export($param, true)) . '</span>';
                }
                $html .= '</div>';
            }

            // Details toggle
            $html .= '<button class="gq-toggle" onclick="toggleDetails(' . $query['id'] . ')">Show Details</button>';
            $html .= '<div class="gq-details" id="details-' . $query['id'] . '">';
            $html .= $this->renderQueryDetails($query['input']);
            $html .= '</div>';

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Render query details
     */
    private function renderQueryDetails(array $input) : string {
        $html = '';

        if (!empty($input['table'])) {
            $html .= '<div class="gq-details-row">';
            $html .= '<span class="gq-details-label">Table:</span>';
            $html .= '<span class="gq-details-value">' . htmlspecialchars($input['table']) . '</span>';
            $html .= '</div>';
        }

        if (!empty($input['alias'])) {
            $html .= '<div class="gq-details-row">';
            $html .= '<span class="gq-details-label">Alias:</span>';
            $html .= '<span class="gq-details-value">' . htmlspecialchars($input['alias']) . '</span>';
            $html .= '</div>';
        }

        if (!empty($input['select']) && $input['select'] !== '*') {
            $html .= '<div class="gq-details-row">';
            $html .= '<span class="gq-details-label">Select:</span>';
            $html .= '<span class="gq-details-value">' . htmlspecialchars($input['select']) . '</span>';
            $html .= '</div>';
        }

        if (!empty($input['where'])) {
            $html .= '<div class="gq-details-row">';
            $html .= '<span class="gq-details-label">Where:</span>';
            $html .= '<span class="gq-details-value">' . htmlspecialchars(implode(' AND ', $input['where'])) . '</span>';
            $html .= '</div>';
        }

        if (!empty($input['joins'])) {
            $html .= '<div class="gq-details-row">';
            $html .= '<span class="gq-details-label">Joins:</span>';
            $html .= '<span class="gq-details-value">' . count($input['joins']) . ' join(s)</span>';
            $html .= '</div>';
        }

        if (!empty($input['groupBy'])) {
            $html .= '<div class="gq-details-row">';
            $html .= '<span class="gq-details-label">Group By:</span>';
            $html .= '<span class="gq-details-value">' . htmlspecialchars($input['groupBy']) . '</span>';
            $html .= '</div>';
        }

        if (!empty($input['orderBy'])) {
            $html .= '<div class="gq-details-row">';
            $html .= '<span class="gq-details-label">Order By:</span>';
            $html .= '<span class="gq-details-value">' . htmlspecialchars($input['orderBy']) . '</span>';
            $html .= '</div>';
        }

        if (!empty($input['limit'])) {
            $html .= '<div class="gq-details-row">';
            $html .= '<span class="gq-details-label">Limit:</span>';
            $html .= '<span class="gq-details-value">' . $input['limit'] . ($input['offset'] ? ' OFFSET ' . $input['offset'] : '') . '</span>';
            $html .= '</div>';
        }

        if (!empty($input['setData'])) {
            $html .= '<div class="gq-details-row">';
            $html .= '<span class="gq-details-label">Set Data:</span>';
            $html .= '<span class="gq-details-value">' . count($input['setData']) . ' column(s)</span>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Render scripts
     */
    private function renderScripts() : string {
        return <<<'HTML'
        <script>
            function toggleDetails(id) {
                var details = document.getElementById('details-' + id);
                details.classList.toggle('active');
            }
        </script>
        HTML;
    }
}
