<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Leyes, Normas y SOAT - Noticias Moto</title>
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

<!-- HEADER MEJORADO -->
<header class="mx-auto bg-white border-b-2 border-green-200 shadow-sm">
  <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
    <div class="flex items-center space-x-4">
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-700 border-2 border-green-600 shadow-md">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
          <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
        </svg>
      </div>      
      <div class="leading-tight">
        <div class="heading-font text-2xl font-bold uppercase tracking-tight text-green-800">Cayman Moto Legal News</div>
        <div class="text-xs text-gray-500 font-medium">Leyes y Normas - Peru</div>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-green-50 px-4 py-1.5 text-sm font-medium text-green-700 border border-green-200">
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
      </span>
    </div>
  </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-8">

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

// Filtrar rápidamente: excluir Infobae y temas sensibles
$raw_articles = isset($news['articles']) && is_array($news['articles']) ? $news['articles'] : [];
$forbidden = ['crimen','delincuen','robo','impuesto','impuestos','homicidio','asesin'];
$articles = [];
foreach ($raw_articles as $r) {
  $txt = mb_strtolower(($r['title']??'') . ' ' . ($r['description']??'') . ' ' . ($r['content']??''));
  $skip = false;
  foreach ($forbidden as $kw) { if (mb_stripos($txt, $kw) !== false) { $skip = true; break; } }
  if ($skip) continue;
  $src = mb_strtolower($r['source']['name'] ?? '');
  // excluir solo cuando el campo source.name contenga 'infobae'
  if (mb_stripos($src, 'infobae') !== false) continue;
  // preferir medios .pe (si deseas forzar .pe, descomenta la línea siguiente)
  //$articles[] = (strpos($url, '.pe')!==false) ? $r : null;
  $articles[] = $r;
}

// Helper: convertir publishedAt UTC -> America/Lima
function parse_published_local($iso)
{
  if (empty($iso)) return null;
  try {
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
?>

<?php if (isset($news['error'])): ?>
  <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow">Error: <?=htmlspecialchars($news['error'])?></div>
<?php else: ?>
  <?php /* usamos $articles filtrados arriba (por source.name) */ ?>
  <?php if (count($articles) > 0): ?>
    <?php $main = $articles[0]; $rest = array_slice($articles, 1); ?>

    <!-- TÍTULO PRINCIPAL MEJORADO (TAMAÑOS MÁS GRANDES) -->
    <div class="text-center mb-10">
      <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4 border-2 border-green-300">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-700 h-8 w-8">
          <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
        </svg>
      </div>
      <h1 class="heading-font text-5xl md:text-6xl font-extrabold tracking-tight mb-3 text-gray-900">LEYES, NORMAS Y <span class="text-green-700">SOAT</span></h1>
      <p class="text-base text-gray-600 max-w-3xl mx-auto leading-relaxed">Información actualizada sobre leyes de tránsito, normas para motociclistas, SOAT y regulaciones vigentes en Perú. Mantente informado para circular de forma segura y legal.</p>
    </div>

    <!-- GRID PRINCIPAL: NOTICIA DESTACADA + ASIDE -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start mb-12">
      <!-- COLUMNA GRANDE: NOTICIA PRINCIPAL (TAMAÑOS MEJORADOS) -->
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
              <span class="bg-green-700 text-white px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wide inline-block mb-4">DESTACADO</span>
              <h2 class="heading-font text-2xl lg:text-3xl font-bold leading-tight mb-2 text-gray-900"><?=htmlspecialchars($main['title'])?></h2>
              <?php $main_dt = parse_published_local($main['publishedAt'] ?? null); if ($main_dt): ?>
                <div class="text-sm text-gray-500 mb-3"><?=htmlspecialchars($main_dt->format('d/m/Y'))?></div>
              <?php endif; ?>
              <p class="text-gray-600 text-base mb-5 leading-relaxed"><?=htmlspecialchars(substr($main['description'] ?? '', 0, 200))?><?=(strlen($main['description'] ?? '')>200?'...':'')?></p>
            </div>
            <div class="flex items-center justify-between border-t border-gray-100 pt-4">
              <span class="text-sm font-semibold text-green-700 uppercase tracking-wider"><?=htmlspecialchars($main['source']['name'] ?? 'Fuente')?></span>
              <a href="<?=htmlspecialchars($main['url'])?>" target="_blank" class="text-green-700 font-bold hover:text-green-800 text-base">Leer más →</a>
            </div>
            <?php $mainMsg = rawurlencode(($main['title'] ?? '') . ' - ' . ($main['url'] ?? '')); ?>
            <a href="https://wa.me/?text=<?=$mainMsg?>" target="_blank" rel="noopener noreferrer" class="mt-5 bg-green-700 hover:bg-green-800 text-white rounded-full py-3 px-5 inline-flex items-center justify-center gap-2 font-semibold text-sm shadow-md transition">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                  <path fill="currentColor" fill-rule="evenodd" d="M12 4a8 8 0 0 0-6.895 12.06l.569.718-.697 2.359 2.32-.648.379.243A8 8 0 1 0 12 4ZM2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10a9.96 9.96 0 0 1-5.016-1.347l-4.948 1.382 1.426-4.829-.006-.007-.033-.055A9.958 9.958 0 0 1 2 12Z" clip-rule="evenodd"/>
                  <path fill="currentColor" d="M16.735 13.492c-.038-.018-1.497-.736-1.756-.83a1.008 1.008 0 0 0-.34-.075c-.196 0-.362.098-.49.291-.146.217-.587.732-.723.886-.018.02-.042.045-.057.045-.013 0-.239-.093-.307-.123-1.564-.68-2.751-2.313-2.914-2.589-.023-.04-.024-.057-.024-.057.005-.021.058-.074.085-.101.08-.079.166-.182.249-.283l.117-.14c.121-.14.175-.25.237-.375l.033-.066a.68.68 0 0 0-.02-.64c-.034-.069-.65-1.555-.715-1.711-.158-.377-.366-.552-.655-.552-.027 0 0 0-.112.005-.137.005-.883.104-1.213.311-.35.22-.94.924-.94 2.16 0 1.112.705 2.162 1.008 2.561l.041.06c1.161 1.695 2.608 2.951 4.074 3.537 1.412.564 2.081.63 2.461.63.16 0 .288-.013.4-.024l.072-.007c.488-.043 1.56-.599 1.804-1.276.192-.534.243-1.117.115-1.329-.088-.144-.239-.216-.43-.308Z"/>
              </svg>
              <span>Compartir por WhatsApp</span>
            </a>
          </div>
        </div>
      </div>

      <!-- ASIDE: MÁS NOTICIAS LEGALES (TAMAÑOS CONSISTENTES) -->
      <aside class="space-y-5">
        <div class="bg-white rounded-2xl p-6 shadow-lg border border-gray-100">
          <h3 class="heading-font text-xl font-bold text-gray-900 mb-5 flex items-center">
            <span class="bg-green-700 w-1.5 h-6 rounded-full inline-block mr-3"></span>
            MÁS NOTICIAS LEGALES
          </h3>
          <div class="space-y-5">
            <?php foreach ($rest as $i => $a): if ($i >= 2) break; ?>
              <div class="flex bg-gray-50 rounded-xl overflow-hidden border border-gray-200 hover:shadow-md transition">
                <?php if (!empty($a['image'])): ?>
                  <div class="w-28 h-28 flex-shrink-0">
                    <img src="<?=htmlspecialchars($a['image'])?>" class="w-full h-full object-cover" alt="">
                  </div>
                <?php else: ?>
                  <div class="w-28 h-28 bg-gray-200 flex-shrink-0"></div>
                <?php endif; ?>
                <div class="p-4 flex-1">
                  <div class="text-xs font-bold text-green-700 uppercase tracking-wider mb-1"><?=htmlspecialchars($a['source']['name'] ?? 'Noticia')?></div>
                  <?php $dt = parse_published_local($a['publishedAt'] ?? null); if ($dt): ?>
                    <div class="text-xs text-gray-400 mb-1"><?=htmlspecialchars($dt->format('d/m/Y'))?></div>
                  <?php endif; ?>
                  <a href="<?=htmlspecialchars($a['url'])?>" target="_blank" class="heading-font font-bold text-base leading-tight text-gray-900 block mb-2 hover:text-green-700"><?=htmlspecialchars($a['title'])?></a>
                  <p class="text-xs text-gray-500 mb-2"><?=htmlspecialchars(substr($a['description'] ?? '', 0, 70))?><?=(strlen($a['description'] ?? '')>70?'...':'')?></p>
                  <?php $sideMsg = rawurlencode(($a['title'] ?? '') . ' - ' . ($a['url'] ?? '')); ?>
                  <a href="https://wa.me/?text=<?=$sideMsg?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs bg-green-100 hover:bg-green-200 text-green-800 px-3 py-1.5 rounded-full font-medium transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 12.5 3 8.5 8.5 0 0 1 21 11.5z"></path></svg>
                    Enviar
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </aside>
    </div>

    <!-- MÁS NOTICIAS (GRID 2 COLUMNAS) - TAMAÑOS PROFESIONALES -->
    <section class="mt-12">
      <h2 class="heading-font text-2xl font-bold text-gray-900 mb-6 flex items-center">
        <span class="bg-green-700 w-1.5 h-7 rounded-full inline-block mr-3"></span>
        ÚLTIMAS NOTICIAS
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($rest as $a): ?>
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
                <div class="text-xs font-bold text-green-700 uppercase mb-1"><?=htmlspecialchars($a['source']['name'] ?? 'Fuente')?></div>
                <?php $dt = parse_published_local($a['publishedAt'] ?? null); if ($dt): ?>
                  <div class="text-xs text-gray-400 mb-1"><?=htmlspecialchars($dt->format('d/m/Y'))?></div>
                <?php endif; ?>
                <a href="<?=htmlspecialchars($a['url'])?>" target="_blank" class="heading-font font-bold text-base text-gray-900 leading-tight block mb-2 hover:text-green-700"><?=htmlspecialchars($a['title'])?></a>
                <p class="text-sm text-gray-600 mb-2"><?=htmlspecialchars(substr($a['description'] ?? '', 0, 90))?><?=(strlen($a['description'] ?? '')>90?'...':'')?></p>
              </div>
            </div>
            <div class="mt-3 flex justify-end">
              <?php $listMsg = rawurlencode(($a['title'] ?? '') . ' - ' . ($a['url'] ?? '')); ?>
              <a href="https://wa.me/?text=<?=$listMsg?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-sm bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-full font-medium transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 12.5 3 8.5 8.5 0 0 1 21 11.5z"></path></svg>
                Compartir
              </a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

  <?php else: ?>
    <div class="bg-white p-8 rounded-2xl shadow text-center text-gray-500">No se encontraron noticias.</div>
  <?php endif; ?>
<?php endif; ?>
</main>

<!-- FOOTER PROFESIONAL -->
<footer class="mt-16 bg-white border-t border-green-100 py-10">
  <div class="max-w-6xl mx-auto px-6 flex flex-col items-center gap-4">
    <div class="flex items-center gap-3">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-700 h-6 w-6">
        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
      </svg>
      <span class="heading-font text-lg font-bold text-green-800">Cayman Moto Legal News</span>
    </div>
    <p class="text-sm text-gray-500">• Información legal para motociclistas</p>
    <p class="text-xs text-gray-400">© 2026 Cayman Moto Legal News - Leyes, Normas y SOAT Perú</p>
  </div>
</footer>

</body>
</html>