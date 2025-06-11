<?php
/**
 * Plugin Name: Log Requests
 * Description: Intercepta REST requests y los guarda en logs diarios usando error_log().
 * Version: 1.2.1
 */

define('RLM_LOG_DIR', WP_CONTENT_DIR . '/logs');

add_action('admin_menu', function () {
	add_menu_page('REST Logs', 'REST Logs', 'manage_options', 'rest-log-viewer', 'rlm_render_admin_page');
});

function rlm_render_admin_page()
{
	date_default_timezone_set('America/Mexico_City'); // Establece timezone CDMX
	$days = 7;
	$log_files = glob(RLM_LOG_DIR . '/rest_*.log');
	if (!$log_files) {
		echo '<div class="wrap"><h1>REST Logs</h1><p>No se encontraron archivos de log REST.</p></div>';
		return;
	}

	$log_files = array_reverse($log_files);
	$selected = $_GET['log'] ?? basename(end($log_files));
	$log_path = RLM_LOG_DIR . '/' . basename($selected);

	// Obtener parámetros de búsqueda y filtros
	$search_query = $_GET['search'] ?? '';
	$status_filter = $_GET['status_filter'] ?? 'all';
	$method_filter = $_GET['method_filter'] ?? 'all';
	$user_filter = $_GET['user_filter'] ?? 'all';

	if (isset($_POST['clean_log'])) {
		file_put_contents($log_path, '');
	}

	if (isset($_POST['delete_old_logs'])) {
		foreach ($log_files as $file) {
			if (filemtime($file) < strtotime("-$days days")) {
				unlink($file);
			}
		}
	}

	if (isset($_POST['download_log'])) {
		if (file_exists($log_path)) {
			header('Content-Type: text/plain');
			header('Content-Disposition: attachment; filename="' . basename($log_path) . '"');
			readfile($log_path);
			exit;
		}
	}

	// Leer y filtrar líneas
	$lines = file_exists($log_path) ? array_reverse(file($log_path)) : [];
	$filtered_lines = filter_log_lines($lines, $search_query, $status_filter, $method_filter, $user_filter);

	$paged = max(1, intval($_GET['paged'] ?? 1));
	$per_page = 20;
	$total = count($filtered_lines);
	$offset = ($paged - 1) * $per_page;
	$current_lines = array_slice($filtered_lines, $offset, $per_page);

	?>
	<div class="wrap">
		<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

		<div class="max-w-6xl mx-auto p-6 bg-white shadow rounded">
			<h1 class="text-2xl font-bold mb-4">REST Logs</h1>

			<form method="post" class="space-y-4 mb-4">
				<div class="flex flex-wrap items-center space-x-2">
					<label class="font-medium">Seleccionar log:</label>
					<select name="log" onchange="this.form.submit()" class="border px-3 py-2 rounded">
						<?php foreach ($log_files as $file):
							$basename = basename($file);
							$selected_attr = ($basename === $selected) ? 'selected' : '';
							?>
							<option value="<?php echo esc_attr($basename); ?>" <?php echo $selected_attr; ?>>
								<?php echo esc_html($basename); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="flex flex-wrap items-center space-x-2 mt-2">
					<button name="clean_log" class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold px-4 py-2 rounded">
						Limpiar log actual
					</button>

					<button name="download_log" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold px-4 py-2 rounded">
						Descargar log actual
					</button>

					<input name="days" type="number" min="1" placeholder="Días" class="border px-2 py-2 rounded" />
					<button name="delete_old_logs" class="bg-red-500 hover:bg-red-600 text-white font-semibold px-4 py-2 rounded">
						Borrar logs antiguos
					</button>
				</div>
			</form>

			<!-- Formulario de búsqueda y filtros -->
			<form method="get" class="bg-gray-50 p-4 rounded mb-4">
				<input type="hidden" name="page" value="rest-log-viewer">
				<input type="hidden" name="log" value="<?php echo esc_attr($selected); ?>">

				<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
					<!-- Campo de búsqueda -->
					<div class="col-span-full">
						<label class="block text-sm font-medium mb-1">Buscar en logs:</label>
						<input type="text" name="search" value="<?php echo esc_attr($search_query); ?>"
						       placeholder="Buscar por URL, IP, mensaje..."
						       class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-blue-500">
					</div>

					<!-- Filtro por estado -->
					<div>
						<label class="block text-sm font-medium mb-1">Estado:</label>
						<select name="status_filter" class="w-full border px-3 py-2 rounded">
							<option value="all" <?php selected($status_filter, 'all'); ?>>Todos</option>
							<option value="2xx" <?php selected($status_filter, '2xx'); ?>>2xx (Éxito)</option>
							<option value="4xx" <?php selected($status_filter, '4xx'); ?>>4xx (Error cliente)</option>
							<option value="5xx" <?php selected($status_filter, '5xx'); ?>>5xx (Error servidor)</option>
						</select>
					</div>

					<!-- Filtro por método -->
					<div>
						<label class="block text-sm font-medium mb-1">Método:</label>
						<select name="method_filter" class="w-full border px-3 py-2 rounded">
							<option value="all" <?php selected($method_filter, 'all'); ?>>Todos</option>
							<option value="GET" <?php selected($method_filter, 'GET'); ?>>GET</option>
							<option value="POST" <?php selected($method_filter, 'POST'); ?>>POST</option>
							<option value="PUT" <?php selected($method_filter, 'PUT'); ?>>PUT</option>
							<option value="DELETE" <?php selected($method_filter, 'DELETE'); ?>>DELETE</option>
						</select>
					</div>

					<!-- Filtro por usuario -->
					<div>
						<label class="block text-sm font-medium mb-1">Usuario:</label>
						<select name="user_filter" class="w-full border px-3 py-2 rounded">
							<option value="all" <?php selected($user_filter, 'all'); ?>>Todos</option>
							<option value="admin" <?php selected($user_filter, 'admin'); ?>>admin</option>
							<option value="editor" <?php selected($user_filter, 'editor'); ?>>editor</option>
							<option value="subscriber" <?php selected($user_filter, 'subscriber'); ?>>subscriber</option>
						</select>
					</div>

					<!-- Botones -->
					<div class="col-span-full flex flex-wrap gap-2">
						<button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
							Buscar
						</button>
						<a href="?page=rest-log-viewer&log=<?php echo esc_attr($selected); ?>"
						   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
							Limpiar filtros
						</a>
					</div>
				</div>
			</form>

			<!-- Resultados -->
			<div class="mb-4">
				<h2 class="text-lg font-semibold">
					Archivo actual: <code class="text-sm bg-gray-100 px-2 py-1 rounded"><?php echo esc_html($selected); ?></code>
					<?php if ($search_query || $status_filter !== 'all' || $method_filter !== 'all' || $user_filter !== 'all'): ?>
						<span class="text-sm text-gray-600 ml-2">
                            (<?php echo $total; ?> resultados encontrados)
                        </span>
					<?php endif; ?>
				</h2>
			</div>

			<div class="border rounded bg-gray-50 p-4 overflow-auto max-h-[400px] text-sm font-mono">
				<?php if (empty($current_lines)): ?>
					<div class="text-gray-500 text-center py-4">No se encontraron resultados</div>
				<?php else: ?>
					<?php foreach ($current_lines as $line): ?>
						<div class="mb-1 border-b border-gray-200 pb-1">
							<?php echo highlight_search_terms(esc_html($line), $search_query); ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<?php if ($total > 0): ?>
				<div class="mt-4 flex space-x-2">
					<?php
					$total_pages = ceil($total / $per_page);
					$base_url = build_search_url($selected, $search_query, $status_filter, $method_filter, $user_filter);

					echo '<div class="mt-4 flex flex-wrap gap-2 items-center">';

					if ($paged > 1) {
						echo '<a href="' . $base_url . '&paged=' . ($paged - 1) . '" class="px-3 py-1 rounded bg-gray-200 text-gray-700">Anterior</a>';
					}

					$range = 2;
					$start = max(1, $paged - $range);
					$end = min($total_pages, $paged + $range);

					if ($start > 1) {
						echo '<a href="' . $base_url . '&paged=1" class="px-3 py-1 rounded bg-gray-200 text-gray-700">1</a>';
						if ($start > 2) {
							echo '<span class="px-2">...</span>';
						}
					}

					for ($i = $start; $i <= $end; $i++) {
						$active = $i === $paged ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700';
						echo '<a href="' . $base_url . '&paged=' . $i . '" class="px-3 py-1 rounded ' . $active . '">' . $i . '</a>';
					}

					if ($end < $total_pages) {
						if ($end < $total_pages - 1) {
							echo '<span class="px-2">...</span>';
						}
						echo '<a href="' . $base_url . '&paged=' . $total_pages . '" class="px-3 py-1 rounded bg-gray-200 text-gray-700">' . $total_pages . '</a>';
					}

					if ($paged < $total_pages) {
						echo '<a href="' . $base_url . '&paged=' . ($paged + 1) . '" class="px-3 py-1 rounded bg-gray-200 text-gray-700">Siguiente</a>';
					}

					echo '</div>';
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

function filter_log_lines($lines, $search_query, $status_filter, $method_filter, $user_filter)
{
	$filtered = [];

	foreach ($lines as $line) {
		$line = trim($line);
		if (empty($line)) continue;

		// Aplicar búsqueda por texto
		if (!empty($search_query)) {
			if (stripos($line, $search_query) === false) {
				continue;
			}
		}

		// Aplicar filtro por estado HTTP
		if ($status_filter !== 'all') {
			if (!preg_match('/\b(' . $status_filter[0] . '\d{2})\b/', $line)) {
				continue;
			}
		}

		// Aplicar filtro por método HTTP
		if ($method_filter !== 'all') {
			if (stripos($line, $method_filter) === false) {
				continue;
			}
		}

		// Aplicar filtro por usuario
		if ($user_filter !== 'all') {
			if (stripos($line, $user_filter) === false) {
				continue;
			}
		}

		$filtered[] = $line;
	}

	return $filtered;
}

function highlight_search_terms($text, $search_query)
{
	if (empty($search_query)) {
		return $text;
	}

	$highlighted = str_ireplace(
		$search_query,
		'<span class="bg-yellow-200 px-1 rounded">' . $search_query . '</span>',
		$text
	);

	return $highlighted;
}

function build_search_url($log, $search, $status, $method, $user)
{
	$params = [
		'page' => 'rest-log-viewer',
		'log' => $log
	];

	if (!empty($search)) {
		$params['search'] = $search;
	}

	if ($status !== 'all') {
		$params['status_filter'] = $status;
	}

	if ($method !== 'all') {
		$params['method_filter'] = $method;
	}

	if ($user !== 'all') {
		$params['user_filter'] = $user;
	}

	return '?' . http_build_query($params);
}
