<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>MotoRadar</title>
  <link rel="shortcut icon" href="logo.png" type="image/x-icon">
  <link rel="apple-touch-icon" href="logo.png" type="image/x-icon">
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700;800&family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }
    h1, h2, h3, .heading-font {
      font-family: 'Oswald', sans-serif;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800">

<?php
// Configuración

$API_TOKEN = 'cae0d16c92d0ada9a3383ac286e831cf';
$query = isset($_GET['q']) && $_GET['q'] !== '' ? $_GET['q'] : 'para motos';
$lang = 'es';
$country = 'pe';
$max = 10;

function fetchNews($token, $q, $lang, $country, $max) {
    $url = 'https://gnews.io/api/v4/search?token=' . urlencode($token)
         . '&q=' . urlencode($q)
         . '&lang=' . urlencode($lang)
         . '&country=' . urlencode($country)
         . '&max=' . intval($max);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['error' => $err ?: 'Error desconocido'];
    }

    $data = json_decode($resp, true);
    if (!$data) return ['error' => 'No se pudo decodificar respuesta JSON'];
    return $data;
}

$news = fetchNews($API_TOKEN, $query, $lang, $country, $max);

// Conexión a la base de datos y función para guardar historial
require_once __DIR__ . '/config.php';

function saveArticlesToDb(array $items)
{
  if (empty($items)) return 0;
  try {
    $pdo = conexion_postgres();
    $pdo->beginTransaction();
    $sql = 'INSERT INTO noticias_historial (id, titulo, link, fecha, diario, resumen, imagen) VALUES (:id, :titulo, :link, :fecha, :diario, :resumen, :imagen) ON CONFLICT (id) DO NOTHING';
    $stmt = $pdo->prepare($sql);
    $inserted = 0;
    foreach ($items as $it) {
      $id = isset($it['id']) ? (string)$it['id'] : null;
      if (!$id) continue;
      $titulo = isset($it['title']) ? mb_substr($it['title'], 0, 1000) : null;
      $link = isset($it['url']) ? $it['url'] : null;
      $fecha = null;
      if (isset($it['publishedAt']) && !empty($it['publishedAt'])) {
        try {
          $dtt = parse_published_local($it['publishedAt']);
          if ($dtt) $fecha = $dtt->format('Y-m-d');
        } catch (Exception $e) { $fecha = null; }
      }
      $diario = isset($it['source']['name']) ? $it['source']['name'] : null;
      $resumen = isset($it['description']) ? mb_substr($it['description'], 0, 4000) : null;
      $imagen = isset($it['image']) ? $it['image'] : null;

      $stmt->execute([
        ':id' => $id,
        ':titulo' => $titulo,
        ':link' => $link,
        ':fecha' => $fecha,
        ':diario' => $diario,
        ':resumen' => $resumen,
        ':imagen' => $imagen
      ]);
      if ($stmt->rowCount() > 0) $inserted++;
    }
    $pdo->commit();
    return $inserted;
  } catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Guardar historial error: ' . $e->getMessage());
    return 0;
  }
}

// Helper: convertir publishedAt UTC -> America/Lima
function parse_published_local($iso)
{
  if (empty($iso)) return null;
  try {
    // Si la cadena ISO contiene información de zona (Z o +HH:MM / -HH:MM), dejar que DateTime la use.
    // Si no contiene zona, asumimos que viene en UTC.
    $hasTz = preg_match('/Z$|[+\-]\d{2}:?\d{2}$/i', $iso);
    if ($hasTz) {
      $dt = new DateTime($iso);
    } else {
      $dt = new DateTime($iso, new DateTimeZone('UTC'));
    }
    $dt->setTimezone(new DateTimeZone('America/Lima'));
    return $dt;
  } catch (Exception $e) {
    return null;
  }
}

// Recuperar artículos guardados en Postgres (mapear al formato de API)
function fetchSavedArticlesFromDb($limit = 20)
{
  try {
    $pdo = conexion_postgres();
    $sql = 'SELECT id, titulo, link, fecha, diario, resumen, imagen FROM noticias_historial ORDER BY fecha DESC, id DESC LIMIT :lim';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
      $publishedAt = null;
      if (!empty($r['fecha'])) {
        // La columna `fecha` almacena solo la fecha (Y-m-d).
        // Para evitar desplazamientos al parsear y convertir zonas, añadimos el tiempo
        // y el offset de Lima (-05:00) al campo publishedAt.
        $publishedAt = $r['fecha'] . 'T00:00:00-05:00';
      }
      $out[] = [
        'id' => $r['id'],
        'title' => $r['titulo'],
        'url' => $r['link'],
        'publishedAt' => $publishedAt,
        'source' => ['name' => $r['diario']],
        'description' => $r['resumen'],
        'image' => $r['imagen']
      ];
    }
    return $out;
  } catch (Exception $e) {
    error_log('fetchSavedArticlesFromDb error: ' . $e->getMessage());
    return [];
  }
}

// Filtrar noticias por la fecha actual (en zona America/Lima)
$articles_for_filter = isset($news['articles']) && is_array($news['articles']) ? $news['articles'] : [];
// Excluir artículos cuyo `source.name` indique "Infobae" (solo por el campo JSON source.name)
$articles_for_filter = array_values(array_filter($articles_for_filter, function($a){
  $s = mb_strtolower($a['source']['name'] ?? '');
  return mb_stripos($s, 'infobae') === false;
}));
$today = (new DateTime('now', new DateTimeZone('America/Lima')))->format('Y-m-d');
$todayArticles = array_filter($articles_for_filter, function($article) use ($today) {
  if (!isset($article['publishedAt']) || empty($article['publishedAt'])) return false;
  $dt = parse_published_local($article['publishedAt']);
  if (!$dt) return false;
  return $dt->format('Y-m-d') === $today;
});
$todayArticles = array_values($todayArticles);

// Guardar siempre (sin duplicados gracias a ON CONFLICT) las primeras N noticias en el historial
try {
  $toSave = array_slice($articles_for_filter, 0, 20);
  if (!empty($toSave)) { saveArticlesToDb($toSave); }
} catch (Exception $e) { error_log('saveArticlesToDb fallo: '.$e->getMessage()); }

// Recuperar historial guardado para mostrar en el sitio (siempre disponible)
$savedArticles = fetchSavedArticlesFromDb(20);
?>

<!-- HEADER MEJORADO - SOLO TAILWIND -->
<header class="bg-white border-b-2 border-green-200 shadow-sm">
  <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
    <div class="flex items-center space-x-4">
      <div class="flex h-15 w-15 ">  <!--items-center justify-center rounded-lg bg-green-700 border-2 border-green-600 shadow-md -->
        <img src="logo.png" alt="icono" width="70" height="70" class="object-contain">
      </div>     
      <div class="leading-tight">
        <div class="heading-font text-2xl font-bold  tracking-tight text-green-800">MotoRadar</div>
        <!-- <div class="text-xs text-gray-500 font-medium">Leyes y Normas - Peru</div> -->
      </div>
    </div>
    <div class="flex items-center gap-3">
      <!-- <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-green-50 px-4 py-1.5 text-sm font-medium text-green-700 border border-green-200">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
          <circle cx="18.5" cy="17.5" r="3.5"></circle>
          <circle cx="5.5" cy="17.5" r="3.5"></circle>
          <circle cx="15" cy="5" r="1"></circle>
          <path d="M12 17.5V14l-3-3 4-3 2 3h2"></path>
        </svg>
        SOAT • Normas • Tránsito
      </span>
      <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-4 py-1.5 text-sm font-medium text-green-800 border border-green-300">
        <span class="h-2 w-2 rounded-full bg-green-600 animate-pulse"></span>
        En vivo
      </span> -->
    </div>
  </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-8">

<?php if (isset($news['error'])): ?>
  <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow">Error: <?=htmlspecialchars($news['error'])?></div>
<?php else: ?>
  <?php $articles = $articles_for_filter; ?>
  <?php
    // Asegurar que no queden fuentes 'infobae' por si acaso
    $articles = array_values(array_filter($articles, function($a){
      $s = mb_strtolower($a['source']['name'] ?? '');
      return mb_stripos($s, 'infobae') === false;
    }));
    $todayArticles = array_values(array_filter($todayArticles, function($a){
      $s = mb_strtolower($a['source']['name'] ?? '');
      return mb_stripos($s, 'infobae') === false;
    }));
  ?>
  <?php if (count($articles) > 0 && count($todayArticles) > 0): ?>
    <?php $main = array_shift($todayArticles); $main_dt = parse_published_local($main['publishedAt'] ?? null); $main_date = $main_dt ? $main_dt->format('Y-m-d') : null; ?>

    <!-- TÍTULO PRINCIPAL - TAMAÑOS MEJORADOS -->
    <div class="text-center mb-10">
      <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4 border-2 border-green-300">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-700 h-8 w-8">
          <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
        </svg>
      </div>
      <h1 class="heading-font text-5xl md:text-6xl font-extrabold tracking-tight mb-3 text-gray-900">LEYES, NORMAS Y <span class="text-green-700">SOAT</span></h1>
      <p class="text-base text-gray-600 max-w-3xl mx-auto leading-relaxed">Información actualizada sobre leyes de tránsito, normas para motociclistas, SOAT y regulaciones vigentes en Perú.</p>
    </div>

    <!-- GRID PRINCIPAL -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start mb-12">
      <!-- NOTICIA DESTACADA -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl overflow-hidden shadow-xl border border-gray-100 flex flex-col lg:flex-row hover:shadow-2xl transition-shadow">
          <div class="lg:w-3/5">
            <?php if (!empty($main['image'])): ?>
              <img src="<?=htmlspecialchars($main['image'])?>" alt="" class="w-full h-96 lg:h-full object-cover">
            <?php else: ?>
              <div class="w-full h-96 lg:h-full bg-gray-200 flex items-center justify-center text-gray-400">Sin imagen</div>
            <?php endif; ?>
          </div>
          <div class="p-7 lg:w-2/5 flex flex-col justify-between">
            <div>
              <div class="flex items-center gap-2 mb-4">
                <span class="bg-green-700 text-white px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wide inline-block">DESTACADO</span>
                <?php if ($main_date === $today): ?>
                  <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-semibold uppercase">NUEVO</span>
                <?php endif; ?>
              </div>
              <p class="text-xs text-gray-500 mb-3">Publicado el <?= $main_dt ? htmlspecialchars($main_dt->format('d/m/Y')) : 'Fecha no disponible' ?></p>
              <h2 class="heading-font text-2xl lg:text-3xl font-bold leading-tight mb-4 text-gray-900"><?=htmlspecialchars($main['title'])?></h2>
              <p class="text-gray-600 text-base mb-5 leading-relaxed"><?=htmlspecialchars(substr($main['description'] ?? '', 0, 200))?><?=(strlen($main['description'] ?? '')>200?'...':'')?></p>
            </div>
            <div class="flex items-center justify-between border-t border-gray-100 pt-4">
              <span class="text-sm font-semibold text-green-700 uppercase tracking-wider"><?=htmlspecialchars($main['source']['name'] ?? 'Fuente')?></span>
              <a href="<?=htmlspecialchars($main['url'])?>" target="_blank" class="text-green-700 font-bold hover:text-green-800 text-base">Leer más →</a>
            </div>
          </div>
        </div>
      </div>

      <!-- ASIDE: MÁS NOTICIAS LEGALES -->
      <aside class="space-y-5">
        <div class="bg-white rounded-2xl p-6 shadow-lg border border-gray-100">
          <h3 class="heading-font text-xl font-bold text-gray-900 mb-5 flex items-center">
            <span class="bg-green-700 w-1.5 h-6 rounded-full inline-block mr-3"></span>
            MÁS NOTICIAS LEGALES
          </h3>
          <div class="space-y-5">
            <?php foreach ($todayArticles as $i => $a): if ($i >= 2) break; ?>
              <div class="flex bg-gray-50 rounded-xl overflow-hidden border border-gray-200 hover:shadow-md transition">
                <?php if (!empty($a['image'])): ?>
                  <div class="w-28 h-28 flex-shrink-0">
                    <img src="<?=htmlspecialchars($a['image'])?>" class="w-full h-full object-cover" alt="">
                  </div>
                <?php else: ?>
                  <div class="w-28 h-28 bg-gray-200 flex-shrink-0"></div>
                <?php endif; ?>
                <div class="p-4 flex-1">
                  <?php $dt_badge = parse_published_local($a['publishedAt'] ?? null); $badge_date = $dt_badge ? $dt_badge->format('Y-m-d') : null; ?>
                  <div class="flex items-center justify-between mb-1">
                    <div>
                      <div class="text-xs font-bold text-green-700 uppercase tracking-wider"><?=htmlspecialchars($a['source']['name'] ?? 'Noticia')?></div>
                      <?php if ($badge_date === $today): ?>
                        <div class="mt-1 inline-block bg-green-100 text-green-800 px-3 py-0.5 rounded-full text-xs font-semibold uppercase">NUEVO</div>
                      <?php endif; ?>
                    </div>
                    <?php if ($dt_badge): ?>
                      <div class="text-xs text-gray-400"><?=htmlspecialchars($dt_badge->format('d/m/Y'))?></div>
                    <?php endif; ?>
                  </div>
                  <a href="<?=htmlspecialchars($a['url'])?>" target="_blank" class="heading-font font-bold text-base leading-tight text-gray-900 block mb-2 hover:text-green-700"><?=htmlspecialchars($a['title'])?></a>
                  <p class="text-xs text-gray-500"><?=htmlspecialchars(substr($a['description'] ?? '', 0, 70))?><?=(strlen($a['description'] ?? '')>70?'...':'')?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </aside>
    </div>

    <!-- SECCIÓN DE NOTICIAS ADICIONALES (si hay más de 3) -->
    <?php if (count($todayArticles) > 2): ?>
    <section class="mt-12">
      <h2 class="heading-font text-2xl font-bold text-gray-900 mb-6 flex items-center">
        <span class="bg-green-700 w-1.5 h-7 rounded-full inline-block mr-3"></span>
        MÁS NOTICIAS DE HOY
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach (array_slice($todayArticles, 2) as $a): ?>
          <article class="bg-white rounded-xl p-5 shadow-md border border-gray-100 hover:shadow-xl transition flex flex-col">
            <div class="flex gap-4">
              <?php if (!empty($a['image'])): ?>
                <div class="w-32 h-24 flex-shrink-0">
                  <img src="<?=htmlspecialchars($a['image'])?>" class="w-full h-full object-cover rounded-lg" alt="">
                </div>
              <?php else: ?>
                <div class="w-32 h-24 bg-gray-100 rounded-lg flex-shrink-0"></div>
              <?php endif; ?>
              <div class="flex-1">
                <div class="flex items-center justify-between mb-1">
                  <div class="text-xs font-bold text-green-700 uppercase"><?=htmlspecialchars($a['source']['name'] ?? 'Fuente')?></div>
                  <?php $dt = parse_published_local($a['publishedAt'] ?? null); if ($dt): ?>
                    <div class="text-xs text-gray-400"><?=htmlspecialchars($dt->format('d/m/Y'))?></div>
                  <?php endif; ?>
                </div>
                <?php $badge_dt = parse_published_local($a['publishedAt'] ?? null); $badge_d = $badge_dt ? $badge_dt->format('Y-m-d') : null; ?>
                <div class="mb-2">
                  <?php if ($badge_d === $today): ?>
                      <span class="bg-green-100 text-green-800 px-3 py-0.5 rounded-full text-xs font-semibold uppercase">NUEVO</span>
                  <?php endif; ?>
                </div>
                <a href="<?=htmlspecialchars($a['url'])?>" target="_blank" class="heading-font font-bold text-base text-gray-900 leading-tight block mb-2 hover:text-green-700"><?=htmlspecialchars($a['title'])?></a>
                <p class="text-sm text-gray-600"><?=htmlspecialchars(substr($a['description'] ?? '', 0, 90))?><?=(strlen($a['description'] ?? '')>90?'...':'')?></p>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  <?php else: ?>
    <div class="bg-white p-8 rounded-2xl shadow-lg text-center text-gray-500 mt-8 border border-gray-100">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
      </svg>
      <p class="text-lg font-medium">No se encontraron noticias de hoy</p>
      <!-- <p class="text-sm text-gray-500 mt-1">Intenta más tarde o revisa otras fuentes</p> -->
    </div>

    <?php if (count($savedArticles) > 0): ?>
      <section class="mt-8">
        <h2 class="heading-font text-2xl font-bold text-gray-900 mb-6 flex items-center">
          <span class="bg-green-700 w-1.5 h-7 rounded-full inline-block mr-3"></span>
          Historial — Noticias anteriores
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <?php foreach (array_slice($savedArticles, 0, 8) as $a): ?>
            <article class="bg-white rounded-xl p-5 shadow-md border border-gray-100 hover:shadow-xl transition flex">
              <div class="w-28 h-20 flex-shrink-0 mr-4">
                <?php if (!empty($a['image'])): ?>
                  <img src="<?=htmlspecialchars($a['image'])?>" class="w-full h-full object-cover rounded-lg" alt="">
                <?php else: ?>
                  <div class="w-full h-full bg-gray-100 rounded-lg"></div>
                <?php endif; ?>
              </div>
              <div class="flex-1">
                <?php $dt = parse_published_local($a['publishedAt'] ?? null); $bdt = $dt ? $dt->format('Y-m-d') : null; ?>
                
          <div class="flex items-center justify-between mb-1">
                  <div>
                      <div class="text-xs font-bold text-green-700 uppercase">
                      <?=htmlspecialchars($a['source']['name'] ?? '')?>
                      </div>
                      <?php if ($dt): ?>
                      <div class="text-xs text-gray-400 mt-1">
                        <?=htmlspecialchars($dt->format('d/m/Y'))?>
                      </div>
                      <?php endif; ?>
                  </div>
            <div>
              <?php if ($bdt === $today): ?>
              <div class="inline-block bg-green-100 text-green-800 px-3 py-0.5 rounded-full text-xs font-semibold uppercase">
              NUEVO
              </div>
              <?php endif; ?>
            </div>
          </div>

                <a href="<?=htmlspecialchars($a['url'])?>" target="_blank" class="heading-font font-bold text-sm text-gray-900 leading-tight block mb-1 hover:text-green-700"><?=htmlspecialchars($a['title'])?></a>
                <p class="text-xs text-gray-500"><?=htmlspecialchars(substr($a['description'] ?? '', 0, 100))?><?=(strlen($a['description'] ?? '')>100?'...':'')?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
</main>

<!-- FOOTER MEJORADO -->
<footer class="mt-16 bg-white border-t border-green-100 py-10">
  <!-- <div class="max-w-6xl mx-auto px-6 flex flex-col items-center gap-4">
    <div class="flex items-center gap-3">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-700 h-6 w-6">
        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
      </svg>
      <span class="heading-font text-lg font-bold text-green-800">Cayman Moto Legal News</span>
    </div>
    <p class="text-sm text-gray-500">• Información legal para motociclistas</p>
    <p class="text-xs text-gray-400">© 2026 Cayman Moto Legal News - Leyes, Normas y SOAT Perú</p>
  </div> -->
</footer>

</body>
</html>