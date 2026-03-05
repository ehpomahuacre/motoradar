<?php
// Obtener la categoría seleccionada
$categoriaSeleccionada = $_GET['categoria'] ?? 'noticias';

// Conexión
require_once 'config.php';
$pdo = conexion_postgres();

// Paginación para historial (solo historial usa paginación)
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$limite = 6;
$offset = ($paginaActual - 1) * $limite;

// Fecha de hoy
$fechaHoy = date('Y-m-d');
// $fechaHoy = date('2026-03-01');

// TODAS las categorías: mostrar últimas 6 artículos como sección principal
$sqlLatest = "SELECT * FROM noticias_historial WHERE lower(categoria) LIKE :categoria ORDER BY fecha DESC LIMIT :limite";
$stmtLatest = $pdo->prepare($sqlLatest);
$stmtLatest->bindValue(':categoria', "%$categoriaSeleccionada%", PDO::PARAM_STR);
$stmtLatest->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmtLatest->execute();
$displayHoy = $stmtLatest->fetchAll(PDO::FETCH_ASSOC);

// Obtener artículos de hoy para marcar con "NUEVO" (solo para Noticias)
$articulosHoy = [];
$showHoy = strtolower($categoriaSeleccionada) === 'noticias';
if ($showHoy) {
    $sqlHoy = "SELECT titulo FROM noticias_historial WHERE lower(categoria) LIKE :categoria AND fecha = :fechaHoy";
    $stmtHoy = $pdo->prepare($sqlHoy);
    $stmtHoy->bindValue(':categoria', "%$categoriaSeleccionada%", PDO::PARAM_STR);
    $stmtHoy->bindValue(':fechaHoy', $fechaHoy, PDO::PARAM_STR);
    $stmtHoy->execute();
    $articulosHoy = array_column($stmtHoy->fetchAll(PDO::FETCH_ASSOC), 'titulo');
}

// Obtener títulos de artículos ya mostrados en la sección principal para evitar duplicados
$titulosExcluidos = array_column($displayHoy, 'titulo');
$excludeCondition = '';
$params = ['categoria' => "%$categoriaSeleccionada%"];

if (!empty($titulosExcluidos)) {
    // Excluir por título para evitar artículos duplicados
    $placeholders = [];
    foreach ($titulosExcluidos as $idx => $titulo) {
        $key = ':titulo_' . $idx;
        $placeholders[] = $key;
        $params[$key] = $titulo;
    }
    $excludeCondition = " AND titulo NOT IN (" . implode(',', $placeholders) . ")";
}

// Contar total de artículos del historial para paginación (sin los de la sección principal)
$sqlTotal = "SELECT COUNT(*) AS total FROM noticias_historial WHERE lower(categoria) LIKE :categoria" . $excludeCondition;
$stmtTotal = $pdo->prepare($sqlTotal);
$stmtTotal->execute($params);
$totalArticulos = (int)($stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$totalPaginas = $totalArticulos ? (int)ceil($totalArticulos / $limite) : 1;

// Consultar historial según página (máx. 6 por página), excluyendo los de la sección principal
$sqlHist = "SELECT * FROM noticias_historial WHERE lower(categoria) LIKE :categoria" . $excludeCondition . " ORDER BY fecha DESC LIMIT :limite OFFSET :offset";
$stmtHist = $pdo->prepare($sqlHist);
$allParams = array_merge($params, [':limite' => $limite, ':offset' => $offset]);
foreach ($allParams as $key => $value) {
    $stmtHist->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtHist->execute();
$rows = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

// Normalizar los campos para usar en plantilla
$allArticles = [];
foreach ($rows as $r) {
    $allArticles[] = [
        'publicado' => $r['fecha'] ?? '',
        'image' => $r['imagen'] ?? '',
        'title' => $r['titulo'] ?? ($r['title'] ?? ''),
        'description' => $r['resumen'] ?? ($r['description'] ?? ''),
        'link' => $r['link'] ?? '#',
        'source' => $r['diario'] ?? '',
    ];
}

// Preparar URL del logo con cache-busting (usa filemtime si el archivo existe)
$logoFile = __DIR__ . './logo.ico';
$logoUrl = 'logo.ico';
if (file_exists($logoFile)) {
    $logoUrl = 'logo.ico?v=' . filemtime($logoFile);
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>MOTO RADAR</title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700;800&family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="<?= htmlspecialchars($logoUrl) ?>" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($logoUrl) ?>" type="image/x-icon">

    <style>
        .heading-font {
            font-family: 'Oswald', sans-serif
        }

        body {
            font-family: Inter, system-ui, -apple-system, Arial, sans-serif;
            background: #f0f9f4;
            color: #111827
        }

        .container {
            max-width: 1100px;
            margin: 28px auto;
            padding: 0 16px
        }

        .heading {
            font-family: Oswald, 'Segoe UI', Arial, sans-serif;
            font-size: 28px;
            color: #065f46;
            margin: 6px 0 18px
        }

        /* ESTILOS ESPECTACULARES PARA LAS CARDS */
        .card-espectacular {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px -5px rgba(6, 95, 70, 0.1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(6, 95, 70, 0.1);
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .card-espectacular:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 30px 40px -10px rgba(6, 95, 70, 0.3);
            border-color: #065f46;
        }

        .card-espectacular::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #065f46, #10b981, #065f46);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .card-espectacular:hover::before {
            transform: scaleX(1);
        }

        .card-image-container {
            position: relative;
            overflow: hidden;
            height: 200px;
        }

        .card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .card-espectacular:hover .card-image {
            transform: scale(1.1);
        }

        .card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, transparent 0%, rgba(0, 0, 0, 0.5) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .card-espectacular:hover .card-overlay {
            opacity: 1;
        }

        .card-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 10;
        }

        .card-source {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            color: #065f46;
            border: 1px solid #065f46;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            letter-spacing: 0.5px;
        }

        .card-new-badge {
            background: linear-gradient(135deg, #065f46, #10b981);
            color: white;
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 15px rgba(6, 95, 70, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 4px 15px rgba(6, 95, 70, 0.4);
            }

            50% {
                box-shadow: 0 4px 25px rgba(16, 185, 129, 0.6);
            }

            100% {
                box-shadow: 0 4px 15px rgba(6, 95, 70, 0.4);
            }
        }

        .card-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: linear-gradient(to bottom, #ffffff, #f9f9f9);
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a2e3f;
            margin-bottom: 10px;
            line-height: 1.3;
            transition: color 0.3s ease;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-espectacular:hover .card-title {
            color: #065f46;
        }

        .card-date {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .card-date span {
            color: #065f46;
            font-weight: 600;
            background: #ecfdf5;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .card-description {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #065f46;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            padding: 10px 0;
            border-top: 1px solid #e5e7eb;
            margin-top: auto;
            transition: all 0.3s ease;
        }

        .card-link:hover {
            color: #10b981;
            gap: 12px;
        }

        .card-link i {
            transition: transform 0.3s ease;
        }

        .card-link:hover i {
            transform: translateX(5px);
        }

        .empty-state {
            background: linear-gradient(135deg, #f9f9f9, #ffffff);
            border-radius: 30px;
            padding: 50px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            border: 1px dashed #065f46;
        }

        .empty-state p:first-of-type {
            font-size: 24px;
            font-weight: 700;
            color: #065f46;
            margin-bottom: 10px;
        }

        .category-btn {
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin: 0 5px;
        }

        .category-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .pagination-btn {
            padding: 10px 18px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .pagination-btn:hover:not(.active) {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* Header / Footer estilo similar a index.php */
        .app-header {
            background: #ffffff;
            border-bottom: 2px solid #d1fae8;
            box-shadow: 0 4px 20px rgba(6, 95, 70, 0.1);
        }

        .app-header-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px
        }

        .app-header .logo {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            background: #065f46;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700
        }

        .app-header .title {
            font-family: Oswald, 'Segoe UI', Arial, sans-serif;
            color: #065f46;
            font-size: 18px;
            font-weight: 700
        }

        .app-footer {
            text-align: center;
            padding: 20px 8px;
            color: #6b7280;
            border-top: 1px solid #f3f4f6;
            margin-top: 28px;
            font-size: 13px
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <header class="bg-white border-b-2 border-green-200 shadow-sm">
        <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="flex h-15 w-15 "><!-- <div class="flex h-12 w-12 items-center justify-center rounded-lg  p-1"> -->
                    <?php if (!empty($logoUrl)): ?>
                        <img src="logo.png" alt="icono" width="70" height="70" class="object-contain">
                        <!-- <img src="logo.png" alt="Logo" class="h-auto w-auto max-h-full max-w-full object-contain" /> -->
                    <?php else: ?>
                        <div class="h-10 w-10 bg-green-600 rounded-md flex items-center justify-center text-white font-bold">MR</div>
                    <?php endif; ?>
                </div>
                <div class="leading-tight">
                    <div class="heading-font text-2xl font-bold uppercase tracking-tight text-green-800">MOTO RADAR</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Botones de categorías -->
    <div class="container mt-6 text-center">
        <a href="?categoria=noticias" class="category-btn <?= $categoriaSeleccionada === 'noticias' ? 'bg-green-600 text-white' : 'bg-white text-green-600 border-2 border-green-600' ?>">Noticias</a>
        <a href="?categoria=blog" class="category-btn <?= $categoriaSeleccionada === 'blog' ? 'bg-green-700 text-white' : 'bg-white text-green-700 border-2 border-green-700' ?>">Blog</a>
        <a href="?categoria=comercial" class="category-btn <?= $categoriaSeleccionada === 'comercial' ? 'bg-green-800 text-white' : 'bg-white text-green-800 border-2 border-green-800' ?>">Comercial</a>
    </div>

    <!-- Contenido dinámico -->
    <div class="container mt-6">
        <h1 class="heading-font text-5xl font-bold text-gray-900 mb-6 text-center relative inline-block w-full">
            <span class="relative z-10"><?= ucfirst($categoriaSeleccionada) ?></span>
            <span class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-24 h-1 bg-gradient-to-r from-green-400 to-green-600 rounded-full"></span>
        </h1>

        <!-- NOTICIAS DE HOY -->
        <div class="mb-12">
            
            <?php if (!empty($displayHoy)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($displayHoy as $hoy): ?>
                        <article class="card-espectacular">
                            <div class="card-image-container">
                                <?php if (!empty($hoy['imagen'])): ?>
                                    <img src="<?= htmlspecialchars($hoy['imagen']) ?>" alt="<?= htmlspecialchars($hoy['titulo']) ?>" class="card-image">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gradient-to-br from-green-100 to-green-200 flex items-center justify-center">
                                        <span class="text-green-600 font-bold text-lg">MOTO RADAR</span>
                                    </div>
                                <?php endif; ?>
                                <div class="card-overlay"></div>

                                <div class="card-badge flex flex-col gap-2">
                                    <?php if (!empty($hoy['diario'])): ?>
                                        <span class="card-source"><?= htmlspecialchars($hoy['diario']) ?></span>
                                    <?php endif; ?>

                                    <?php if (!empty($hoy['fecha']) && $hoy['fecha'] === $fechaHoy): ?>
                                        <span class="card-new-badge">
                                            <span class="h-2 w-2 rounded-full bg-white animate-pulse"></span>
                                            NUEVO
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="card-content">
                                <h3 class="card-title"><?= htmlspecialchars($hoy['titulo']) ?></h3>
                                <div class="card-date">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span><?= htmlspecialchars($hoy['fecha']) ?></span>
                                </div>
                                <p class="card-description"><?= htmlspecialchars($hoy['resumen']) ?></p>
                                <a href="<?= htmlspecialchars($hoy['link']) ?>" target="_blank" class="card-link">
                                    Leer más
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                    </svg>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="text-6xl mb-4">📰</div>
                    <p class="text-lg font-medium text-gray-700">No se encontraron noticias de hoy</p>
                    <p class="text-sm text-gray-500">Intenta más tarde o revisa otras fuentes</p>
                    <div class="mt-6">
                        <a href="#historial" class="inline-flex items-center gap-2 text-green-600 font-bold hover:text-green-700">
                            Ver historial de noticias
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Historial -->
        <div id="historial">
            <h2 class="heading-font text-3xl font-bold text-gray-800 mb-8 flex items-center">
                <span class="bg-gray-400 w-2 h-8 rounded-full mr-3"></span>
                Historial — <?= ucfirst($categoriaSeleccionada) ?> anteriores
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($allArticles as $index => $a): ?>
                    <article class="card-espectacular" style="animation: fadeInUp 0.5s ease <?= $index * 0.1 ?>s both;">
                        <div class="card-image-container">
                            <?php if (!empty($a['image'])): ?>
                                <img src="<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['title']) ?>" class="card-image">
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                    <span class="text-gray-600 font-bold text-lg">MOTO RADAR</span>
                                </div>
                            <?php endif; ?>
                            <div class="card-overlay"></div>

                            <div class="card-badge">
                                <?php if (!empty($a['source'])): ?>
                                    <span class="card-source"><?= htmlspecialchars($a['source']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-content">
                            <h3 class="card-title"><?= htmlspecialchars($a['title']) ?></h3>
                            <div class="card-date">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span><?= htmlspecialchars($a['publicado']) ?></span>
                            </div>
                            <p class="card-description"><?= htmlspecialchars($a['description']) ?></p>
                            <a href="<?= htmlspecialchars($a['link']) ?>" target="_blank" class="card-link">
                                Leer más
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                </svg>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Paginación (solo para historial) -->
            <?php if ($totalPaginas > 1): ?>
                <div class="mt-12 flex justify-center items-center space-x-3">
                    <?php if ($paginaActual > 1): ?>
                        <a href="?categoria=<?= urlencode($categoriaSeleccionada) ?>&pagina=<?= $paginaActual - 1 ?>" class="pagination-btn bg-white text-gray-700 border border-gray-300 hover:border-green-500 hover:text-green-600">
                            ← Anterior
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <a href="?categoria=<?= urlencode($categoriaSeleccionada) ?>&pagina=<?= $i ?>"
                            class="pagination-btn <?= $i === $paginaActual ? 'bg-green-600 text-white active' : 'bg-white text-gray-700 border border-gray-300 hover:border-green-500 hover:text-green-600' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($paginaActual < $totalPaginas): ?>
                        <a href="?categoria=<?= urlencode($categoriaSeleccionada) ?>&pagina=<?= $paginaActual + 1 ?>" class="pagination-btn bg-white text-gray-700 border border-gray-300 hover:border-green-500 hover:text-green-600">
                            Siguiente →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="mt-16 bg-white border-t border-green-100 py-10">
        <div class="max-w-6xl mx-auto px-6 flex flex-col items-center gap-4">
            <div class="flex items-center gap-3">
                <span class="heading-font text-lg font-bold text-green-800">MOTO RADAR</span>
            </div>
            <p class="text-sm text-gray-500">• Información legal para motociclistas</p>
            <p class="text-xs text-gray-400">© 2026 MOTO RADAR</p>
        </div>
    </footer>

    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</body>

</html>
