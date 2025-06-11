<?php

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

    $lines = file_exists($log_path) ? array_reverse(file($log_path)) : [];
    $paged = max(1, intval($_GET['paged'] ?? 1));
    $per_page = 20;
    $total = count($lines);
    $offset = ($paged - 1) * $per_page;
    $current_lines = array_slice($lines, $offset, $per_page);

    ?>
    <div class="wrap">
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

        <div class="max-w-4xl mx-auto p-6 bg-white shadow rounded">
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


            <div class="mb-4">
                <h2 class="text-lg font-semibold">Archivo actual: <code class="text-sm bg-gray-100 px-2 py-1 rounded"><?php echo esc_html($selected); ?></code></h2>
            </div>

            <div class="border rounded bg-gray-50 p-4 overflow-auto max-h-[400px] text-sm font-mono">
                <?php foreach ($current_lines as $line): ?>
                    <div class="mb-1 border-b border-gray-200 pb-1"><?php echo esc_html($line); ?></div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 flex space-x-2">
                <?php
                $total_pages = ceil($total / $per_page);
                $base_url = '?page=rest-log-viewer&log=' . esc_attr($selected) . '&paged=';

                echo '<div class="mt-4 flex flex-wrap gap-2 items-center">';

                if ($paged > 1) {
	                echo '<a href="' . $base_url . ($paged - 1) . '" class="px-3 py-1 rounded bg-gray-200 text-gray-700">Anterior</a>';
                }

                $range = 2; // cuántos botones a la izquierda y derecha del actual
                $start = max(1, $paged - $range);
                $end = min($total_pages, $paged + $range);

                if ($start > 1) {
	                echo '<a href="' . $base_url . '1" class="px-3 py-1 rounded bg-gray-200 text-gray-700">1</a>';
	                if ($start > 2) {
		                echo '<span class="px-2">...</span>';
	                }
                }

                for ($i = $start; $i <= $end; $i++) {
	                $active = $i === $paged ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700';
	                echo '<a href="' . $base_url . $i . '" class="px-3 py-1 rounded ' . $active . '">' . $i . '</a>';
                }

                if ($end < $total_pages) {
	                if ($end < $total_pages - 1) {
		                echo '<span class="px-2">...</span>';
	                }
	                echo '<a href="' . $base_url . $total_pages . '" class="px-3 py-1 rounded bg-gray-200 text-gray-700">' . $total_pages . '</a>';
                }

                if ($paged < $total_pages) {
	                echo '<a href="' . $base_url . ($paged + 1) . '" class="px-3 py-1 rounded bg-gray-200 text-gray-700">Siguiente</a>';
                }

                echo '</div>';
                ?>



            </div>
        </div>
    </div>
    <?php
}
