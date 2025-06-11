<?php
/**
 * Plugin Name: Log Requests
 * Description: Intercepta REST requests y los guarda en logs diarios usando error_log().
 * Version: 1.1.2
 */

define('RLM_LOG_DIR', WP_CONTENT_DIR . '/logs');

require_once __DIR__ . '/includes/class-rest-log-admin.php';

add_filter('rest_pre_dispatch', function($result, $server, $request) {
	// Solo procesar rutas que contengan "/wp-json/wp"
	$route = $request->get_route();
	if (!str_starts_with($route, '/wp/v2')) {
		return $result;
	}

	date_default_timezone_set('America/Mexico_City');

    $log_data = [
	    'timestamp' => date('Y-m-d H:i:s'),
        'method'    => $request->get_method(),
        'route'     => $request->get_route(),
        'params'    => $request->get_params(),
        'user_id'   => get_current_user_id(),
        'ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
    ];
    rlm_log_to_file(json_encode($log_data, JSON_UNESCAPED_UNICODE));
    return $result;
}, 10, 3);

function rlm_log_to_file($message) {
	date_default_timezone_set('America/Mexico_City'); // Establece timezone CDMX
    if (!file_exists(RLM_LOG_DIR)) {
        mkdir(RLM_LOG_DIR, 0755, true);
    }
    $log_file = RLM_LOG_DIR . '/rest_' . date('Y-m-d') . '.log';
    error_log($message . PHP_EOL, 3, $log_file);
}
