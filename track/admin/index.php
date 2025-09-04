<?php
require_once 'db.php';

// Глобальные настройки
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings");
    $stmt->execute();
    $global_settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log('Failed to fetch global settings: ' . $e->getMessage());
    $global_settings_raw = [];
}

$global_settings = array_map(function($v) {
    if ($v === 'true') return true;
    if ($v === 'false') return false;
    $decoded = json_decode($v, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $v;
}, $global_settings_raw);

// Партнеры
try {
    $stmt = $pdo->prepare("SELECT id, name FROM partners ORDER BY name ASC");
    $stmt->execute();
    $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Failed to fetch partners: ' . $e->getMessage());
    $partners = [];
}

// Агрегированная статистика (Успехи/Ошибки)
try {
    $stmt = $pdo->prepare("SELECT * FROM summary_stats");
    $stmt->execute();
    $summary_stats = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
} catch (PDOException $e) {
    error_log('Failed to fetch summary stats: ' . $e->getMessage());
    $summary_stats = [];
}

$global_stats = ['total_requests' => 0, 'successful_redirects' => 0, 'errors' => 0];
foreach ($summary_stats as $p_stat) {
    $global_stats['total_requests'] += (int)($p_stat['total_requests'] ?? 0);
    $global_stats['successful_redirects'] += (int)($p_stat['successful_redirects'] ?? 0);
    $global_stats['errors'] += (int)($p_stat['errors'] ?? 0);
}

$today_start = date('Y-m-d 00:00:00');
$month_start = date('Y-m-01 00:00:00');

// Подсчет конверсий для карточек партнеров
$stmt_counts = $pdo->prepare("
    SELECT
        partner_id,
        SUM(CASE WHEN `timestamp` >= :today_start1 AND `sum` IS NOT NULL AND `sum` != '' THEN 1 ELSE 0 END) as today_sum_count,
        SUM(CASE WHEN `timestamp` >= :month_start1 AND `sum` IS NOT NULL AND `sum` != '' THEN 1 ELSE 0 END) as month_sum_count,
        SUM(CASE WHEN `timestamp` >= :today_start2 AND `sum_mapping` IS NOT NULL AND `sum_mapping` != '' THEN 1 ELSE 0 END) as today_summap_count,
        SUM(CASE WHEN `timestamp` >= :month_start2 AND `sum_mapping` IS NOT NULL AND `sum_mapping` != '' THEN 1 ELSE 0 END) as month_summap_count
    FROM detailed_stats
    GROUP BY partner_id
");
$stmt_counts->execute([
    'today_start1' => $today_start, 
    'month_start1' => $month_start,
    'today_start2' => $today_start, 
    'month_start2' => $month_start
]);
$counts = $stmt_counts->fetchAll(PDO::FETCH_ASSOC);

// Преобразуем результат в удобный формат для доступа по partner_id
$partner_counts = [];
foreach ($counts as $count) {
    $partner_counts[$count['partner_id']] = $count;
}

// ИСПРАВЛЕННЫЙ ЗАПРОС: Подсчет профита для дашборда с COALESCE и уникальными плейсхолдерами
$stmt_profit = $pdo->prepare("
    SELECT
        (SUM(CASE WHEN `timestamp` >= :today_start1 THEN CAST(COALESCE(sum, 0) AS DECIMAL(10,2)) ELSE 0 END) - SUM(CASE WHEN `timestamp` >= :today_start2 THEN CAST(COALESCE(sum_mapping, 0) AS DECIMAL(10,2)) ELSE 0 END)) as today_profit,
        (SUM(CASE WHEN `timestamp` >= :month_start1 THEN CAST(COALESCE(sum, 0) AS DECIMAL(10,2)) ELSE 0 END) - SUM(CASE WHEN `timestamp` >= :month_start2 THEN CAST(COALESCE(sum_mapping, 0) AS DECIMAL(10,2)) ELSE 0 END)) as month_profit
    FROM detailed_stats
");
$stmt_profit->execute([
    'today_start1' => $today_start, 
    'today_start2' => $today_start,
    'month_start1' => $month_start,
    'month_start2' => $month_start
]);
$profit_stats = $stmt_profit->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta name="description" content="Панель управления аффилиатной системой">
    <meta name="theme-color" content="#0066cc">
    <title>Панель управления</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://cdn.datatables.net/2.3.3/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/responsive/3.1.0/css/responsive.bootstrap5.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/colreorder/2.1.1/css/colReorder.bootstrap5.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="hold-transition sidebar-mini">
<!-- Skip navigation for accessibility -->
<a href="#main-content" class="skip-nav">Перейти к основному содержанию</a>
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-dark">
        <ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li></ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="#" id="theme-toggle" role="button" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="bottom" 
                   title="Переключить тему">
                    <i class="fas fa-moon"></i>
                </a>
            </li>
        </ul>
    </nav>
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-bs-toggle="tab" role="tablist">
                    <li class="nav-item"><a href="#" class="nav-link active main-nav-link" data-bs-target="#dashboard-pane" data-bs-toggle="tab" role="tab"><i class="nav-icon fas fa-tachometer-alt"></i><p>Дашборд</p></a></li>
                    <li class="nav-header">ПАРТНЕРЫ</li>
                    <?php foreach ($partners as $partner): ?>
                        <li class="nav-item"><a href="#" class="nav-link main-nav-link" data-bs-target="#partner-pane-<?= htmlspecialchars($partner['id']) ?>" data-bs-toggle="tab" role="tab"><i class="nav-icon fas fa-user-tie"></i><p><?= htmlspecialchars($partner['name']) ?></p></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <section class="content pt-3" id="main-content">
            <div class="container-fluid">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="dashboard-pane" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?= $global_stats['total_requests'] ?? 0 ?></h3><p>Всего запросов</p></div><div class="icon"><i class="fas fa-chart-bar"></i></div></div></div>
                        <div class="col-lg-3 col-6"><div class="small-box bg-success"><div class="inner"><h3><?= $global_stats['successful_redirects'] ?? 0 ?></h3><p>Успешных редиректов</p></div><div class="icon"><i class="fas fa-check"></i></div></div></div>
                        <div class="col-lg-3 col-6"><div class="small-box bg-info"><div class="inner"><h3><?= number_format((float)($profit_stats['today_profit'] ?? 0), 2) ?></h3><p>Профит за сегодня</p></div><div class="icon"><i class="fas fa-wallet"></i></div></div></div>
                        <div class="col-lg-3 col-6"><div class="small-box bg-secondary"><div class="inner"><h3><?= number_format((float)($profit_stats['month_profit'] ?? 0), 2) ?></h3><p>Профит за месяц</p></div><div class="icon"><i class="fas fa-coins"></i></div></div></div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card card-primary card-outline h-100"><div class="card-header"><h3 class="card-title"><i class="fas fa-globe"></i> Глобальные настройки</h3></div><div class="card-body">
                                <form id="globalSettingsForm">
                                    <h5 class="mb-3">Telegram</h5>
                                    <div class="mb-3"><label for="global_telegram_bot_token" class="form-label">Bot Token</label><input type="text" class="form-control" name="telegram_bot_token" id="global_telegram_bot_token" value="<?= htmlspecialchars($global_settings['telegram_bot_token'] ?? '') ?>"></div>
                                    <div class="mb-3"><label for="global_telegram_channel_id" class="form-label">Channel ID</label><input type="text" class="form-control" name="telegram_channel_id" id="global_telegram_channel_id" value="<?= htmlspecialchars($global_settings['telegram_channel_id'] ?? '') ?>"></div>
                                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="telegram_globally_enabled" id="telegramGloballyEnabled" <?= ($global_settings['telegram_globally_enabled'] ?? false) ? 'checked' : '' ?>><label class="form-check-label" for="telegramGloballyEnabled"><b>Включить отправку в Telegram глобально</b></label></div>

                                    <hr>
                                    <h5 class="mb-3">Настройки cURL</h5>
                                    <div class="row"><div class="col-md-6 mb-3"><label for="curlTimeout" class="form-label">Общий таймаут (сек)</label><input type="number" class="form-control" name="curl_timeout" id="curlTimeout" value="<?= htmlspecialchars($global_settings['curl_timeout'] ?? 10) ?>" min="1"></div><div class="col-md-6 mb-3"><label for="curlConnectTimeout" class="form-label">Таймаут соединения (сек)</label><input type="number" class="form-control" name="curl_connect_timeout" id="curlConnectTimeout" value="<?= htmlspecialchars($global_settings['curl_connect_timeout'] ?? 5) ?>" min="1"></div></div>
                                    <div class="row"><div class="col-md-4"><div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="curl_returntransfer" id="curlReturntransfer" <?= ($global_settings['curl_returntransfer'] ?? true) ? 'checked' : '' ?>><label class="form-check-label" for="curlReturntransfer">RETURNTRANSFER</label></div></div><div class="col-md-4"><div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="curl_followlocation" id="curlFollowlocation" <?= ($global_settings['curl_followlocation'] ?? true) ? 'checked' : '' ?>><label class="form-check-label" for="curlFollowlocation">FOLLOWLOCATION</label></div></div><div class="col-md-4"><div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="curl_ssl_verify" id="curlSslVerify" <?= ($global_settings['curl_ssl_verify'] ?? true) ? 'checked' : '' ?>><label class="form-check-label" for="curlSslVerify">SSL Verify</label></div></div></div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Сохранить</button>
                                </form>
                            </div></div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card card-primary card-outline h-100"><div class="card-header"><h3 class="card-title"><i class="fas fa-users"></i> Партнеры</h3><div class="card-tools"><button class="btn btn-success btn-sm" id="addPartnerBtn"><i class="fas fa-plus"></i> Добавить</button></div></div><div class="card-body table-responsive p-0">
                                <table class="table table-hover text-nowrap">
                                    <thead><tr><th>Партнер</th><th>Ссылка для постбека</th><th class="text-center">Действия</th></tr></thead>
                                    <tbody id="partnersTableBody">
                                    <?php foreach ($partners as $partner): ?>
                                        <tr data-id="<?= htmlspecialchars($partner['id']) ?>">
                                            <td class="align-middle">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <strong><?= htmlspecialchars($partner['name']) ?></strong>
                                                        <br>
                                                        <small class="text-muted d-block">ID: <code><?= htmlspecialchars($partner['id']) ?></code></small>
                                                        
                                                        <div class="partner-stats mt-2" 
                                                             data-bs-toggle="tooltip" 
                                                             data-bs-placement="top" 
                                                             title="Sum: количество записей с параметром sum | Map: количество записей с sum_mapping">
                                                            <div class="stat-item">
                                                                <span class="stat-label">
                                                                    <i class="fas fa-calendar-day me-1"></i>Сегодня
                                                                </span>
                                                                <span class="stat-values">
                                                                    <span class="sum-count"><?= $partner_counts[$partner['id']]['today_sum_count'] ?? 0 ?></span>
                                                                    <span class="text-muted mx-1">/</span>
                                                                    <span class="map-count"><?= $partner_counts[$partner['id']]['today_summap_count'] ?? 0 ?></span>
                                                                </span>
                                                            </div>
                                                            <div class="stat-item">
                                                                <span class="stat-label">
                                                                    <i class="fas fa-calendar-alt me-1"></i>Месяц
                                                                </span>
                                                                <span class="stat-values">
                                                                    <span class="sum-count"><?= $partner_counts[$partner['id']]['month_sum_count'] ?? 0 ?></span>
                                                                    <span class="text-muted mx-1">/</span>
                                                                    <span class="map-count"><?= $partner_counts[$partner['id']]['month_summap_count'] ?? 0 ?></span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php $postbackUrl = "https://{$_SERVER['HTTP_HOST']}/track/postback.php?pid=" . urlencode($partner['id']); ?><div class="input-group input-group-sm"><input type="text" class="form-control" value="<?= $postbackUrl ?>&clickid=..." readonly><button class="btn btn-outline-secondary copy-btn" type="button" data-bs-toggle="tooltip" title="Копировать"><i class="fas fa-clipboard"></i></button></div></td>
                                            <td class="text-center align-middle"><div class="btn-group"><button class="btn btn-primary btn-sm edit-btn" data-bs-toggle="tooltip" title="Редактировать"><i class="fas fa-pencil-alt"></i></button><button class="btn btn-warning btn-sm clear-stats-btn" data-bs-toggle="tooltip" title="Очистить статистику"><i class="fas fa-eraser"></i></button><button class="btn btn-danger btn-sm delete-btn" data-bs-toggle="tooltip" title="Удалить"><i class="fas fa-trash"></i></button></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div></div>
                        </div>
                    </div>
                </div>
                <?php foreach ($partners as $partner): $partner_id_safe = htmlspecialchars($partner['id']); ?>
                <div class="tab-pane fade" id="partner-pane-<?= $partner_id_safe ?>" role="tabpanel" data-partner-id="<?= $partner_id_safe ?>">
                    <h4>Статистика: <?= htmlspecialchars($partner['name']) ?></h4>
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-filter"></i> Фильтры и управление</h3>
                            <!-- Mobile filter toggle button -->
                            <button class="mobile-filter-toggle d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#filter-collapse-<?= $partner_id_safe ?>" aria-expanded="false" aria-controls="filter-collapse-<?= $partner_id_safe ?>">
                                <i class="fas fa-filter me-2"></i>Показать фильтры
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Collapsible filter container for mobile -->
                            <div class="collapse d-md-block filter-collapse" id="filter-collapse-<?= $partner_id_safe ?>">
                                <form class="filter-form" onsubmit="event.preventDefault();">
                                    <div class="row g-3">
                                        <div class="col-xl col-lg-4 col-md-6 col-12">
                                            <label class="form-label">Умный поиск</label>
                                            <input type="text" class="form-control stats-filter-search" placeholder="param:sale, clickid:123, EMPTY...">
                                        </div>
                                        <div class="col-xl-auto col-lg-3 col-md-6 col-12">
                                            <label class="form-label">Статус</label>
                                            <select class="form-select stats-filter">
                                                <option value="all">Все</option>
                                                <option value="200">200 OK</option>
                                                <option value="403">403 Forbidden</option>
                                                <option value="500">500 Error</option>
                                            </select>
                                        </div>
                                        <div class="col-xl-auto col-lg-3 col-md-6 col-12">
                                            <label class="form-label">Дата от</label>
                                            <input type="date" class="form-control stats-filter">
                                        </div>
                                        <div class="col-xl-auto col-lg-2 col-md-6 col-12">
                                            <label class="form-label">Дата до</label>
                                            <input type="date" class="form-control stats-filter">
                                        </div>
                                        <div class="col-xl-auto col-lg-12 col-12 d-flex align-items-end mt-lg-0 mt-3">
                                            <div class="btn-group w-100 mobile-flex-column" role="group">
                                                <button type="button" class="btn btn-primary btn-apply-filters">
                                                    <i class="fas fa-check me-1"></i>Применить
                                                </button>
                                                <button type="button" class="btn btn-secondary btn-reset-filters">
                                                    <i class="fas fa-times me-1"></i>Сбросить
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-3 mt-3">
                                        <div class="col-lg-auto col-md-12 col-12 d-flex align-items-center mobile-flex-column">
                                            <div class="form-check form-switch me-3 mobile-mb-2">
                                                <input class="form-check-input table-refresh-toggle" type="checkbox" role="switch">
                                                <label class="form-check-label">Автообновление</label>
                                            </div>
                                            <select class="form-select form-select-sm table-refresh-interval mobile-w-100" style="max-width: 150px;">
                                                <option value="15000">15 сек</option>
                                                <option value="30000" selected>30 сек</option>
                                                <option value="60000">60 сек</option>
                                            </select>
                                        </div>
                                        <div class="col-lg-auto col-md-12 col-12 d-flex align-items-center">
                                            <div class="dropdown w-100">
                                                <button class="btn btn-secondary dropdown-toggle btn-sm w-100 mobile-w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                                                    <i class="fas fa-columns me-2"></i>Столбцы
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-dark columns-dropdown p-2"></div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card card-primary card-outline mt-4">
                        <div class="card-body p-0">
                            <!-- Responsive table wrapper - only table scrolls -->
                            <div class="table-responsive table-container">
                                <table id="table-<?= $partner_id_safe ?>" class="table table-bordered table-hover w-100">
                                    <thead>
                                        <tr>
                                            <th>Дата</th>
                                            <th>Click ID</th>
                                            <th>URL</th>
                                            <th>Доп. пар.</th>
                                            <th>Статус</th>
                                            <th>Ответ</th>
                                            <th>Sum</th>
                                            <th>Sum Map</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="6" class="text-end border-end-0">Итого (по фильтру):</th>
                                            <th class="sum-total text-end"></th>
                                            <th class="sum-mapping-total text-end"></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <!-- Pagination and controls outside the scrollable area -->
                            <div class="table-controls p-3" id="table-controls-<?= $partner_id_safe ?>"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="partnerModal" tabindex="-1" aria-hidden="true" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="partnerModalLabel">Добавить партнера</h5>
                <button type="button" class="modal-close-emoji-btn" data-bs-dismiss="modal" aria-label="Close">❌</button>
            </div>
            <div class="modal-body">
                <form id="partnerForm" onsubmit="return false;">
                    <input type="hidden" id="partnerOldId">
                    <div class="card card-primary card-tabs">
                        <div class="card-header p-0 pt-1"><ul class="nav nav-tabs" id="partnerTab" role="tablist"><li class="nav-item"><a class="nav-link active" id="main-tab" data-bs-toggle="tab" href="#main-tab-pane" role="tab"><i class="fas fa-info-circle me-2"></i>Основные</a></li><li class="nav-item"><a class="nav-link" id="url-tab" data-bs-toggle="tab" href="#url-tab-pane" role="tab"><i class="fas fa-link me-2"></i>Параметры URL</a></li><li class="nav-item"><a class="nav-link" id="access-tab" data-bs-toggle="tab" href="#access-tab-pane" role="tab"><i class="fas fa-shield-alt me-2"></i>Доступ</a></li><li class="nav-item"><a class="nav-link" id="integrations-tab" data-bs-toggle="tab" href="#integrations-tab-pane" role="tab"><i class="fas fa-cogs me-2"></i>Интеграции</a></li></ul></div>
                        <div class="card-body"><div class="tab-content" id="partnerTabContent">
                            <div class="tab-pane fade show active" id="main-tab-pane" role="tabpanel">
                                <div class="row"><div class="col-md-6 mb-3"><label for="partnerId" class="form-label">ID Партнера* (уникальный)</label><input type="text" class="form-control" id="partnerId" required></div><div class="col-md-6 mb-3"><label for="partnerName" class="form-label">Имя партнера*</label><input type="text" class="form-control" id="partnerName" required></div></div>
                                <div class="mb-3"><label for="targetDomain" class="form-label">Целевой домен*</label><input type="text" class="form-control" id="targetDomain" placeholder="affisevents-pb.com/track/a" required></div>
                                <div class="mb-3"><label for="partnerNotes" class="form-label">Заметки</label><textarea class="form-control" id="partnerNotes" rows="3"></textarea></div>
                            </div>
                            <div class="tab-pane fade" id="url-tab-pane" role="tabpanel">
                                <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Ключи для ClickID</label><div class="tags-input-wrapper"><input id="clickidKeys" placeholder="clickid, cid..."></div></div><div class="col-md-6 mb-3"><label class="form-label">Ключи для Sum</label><div class="tags-input-wrapper"><input id="sumKeys" placeholder="sum, payout..."></div></div></div>
                                <div class="mb-3"><label class="form-label">Маппинг Sum</label><div id="sumMappingContainer"></div><button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="addSumMappingBtn"><i class="fas fa-plus me-2"></i>Добавить маппинг</button></div>
                            </div>
                            <div class="tab-pane fade" id="access-tab-pane" role="tabpanel">
                                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="ipWhitelistEnabled"><label class="form-check-label" for="ipWhitelistEnabled">Включить фильтрацию по IP</label></div>
                                <div class="mb-3"><label class="form-label">Разрешенные IP</label><div class="tags-input-wrapper"><input id="allowedIps" placeholder="64.227.66.201..."></div></div>
                            </div>
                            <div class="tab-pane fade" id="integrations-tab-pane" role="tabpanel">
                                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="loggingEnabled" checked><label class="form-check-label" for="loggingEnabled">Включить логирование в файл</label></div><hr>
                                <h6>Google Sheets</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="googleSpreadsheetId" class="form-label">ID Таблицы</label>
                                        <input type="text" class="form-control" id="googleSpreadsheetId" placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="googleSheetName" class="form-label">Имя Листа</label>
                                        <input type="text" class="form-control" id="googleSheetName" placeholder="Лист1">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="googleServiceAccountJson" class="form-label">Google Service Account JSON</label>
                                    <textarea class="form-control" id="googleServiceAccountJson" rows="8" placeholder='{
  "type": "service_account",
  "project_id": "your-project-id",
  "private_key_id": "...",
  "private_key": "...",
  "client_email": "...",
  ...
}'></textarea>
                                    <div class="form-text">Вставьте JSON содержимое файла service account из Google Cloud Console</div>
                                </div><hr>
                                <h6>Глобальные уведомления Telegram</h6>
                                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="telegramEnabled" checked><label class="form-check-label" for="telegramEnabled">Включить Telegram для этого партнера (глобальный бот)</label></div>
                                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="telegramWhitelistEnabled"><label class="form-check-label" for="telegramWhitelistEnabled">Фильтрация Telegram по "белому списку" (для глобального бота)</label></div>
                                <div class="mb-3"><label class="form-label">Ключевые слова "белого списка"</label><div class="tags-input-wrapper"><input id="telegramWhitelistKeywords" placeholder="Purchase..."></div></div><hr>
                                <h6>Индивидуальные уведомления Telegram</h6>
                                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="partnerTelegramEnabled"><label class="form-check-label" for="partnerTelegramEnabled"><b>Включить индивидуальную отправку для партнера</b></label></div>
                                <div class="row"><div class="col-md-6 mb-3"><label for="partnerTelegramBotToken" class="form-label">Индивидуальный Bot Token</label><input type="text" class="form-control" id="partnerTelegramBotToken"></div><div class="col-md-6 mb-3"><label for="partnerTelegramChannelId" class="form-label">Индивидуальный Channel ID</label><input type="text" class="form-control" id="partnerTelegramChannelId"></div></div>
                            </div>
                        </div></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button><button type="button" class="btn btn-primary" id="savePartnerBtn"><span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span><i class="fas fa-save me-2"></i>Сохранить</button></div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
    <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header"><strong class="me-auto" id="toastTitle">Уведомление</strong><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>
<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/2.3.3/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.3/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.1.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.1.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/colreorder/2.1.1/js/dataTables.colReorder.min.js"></script>
<script src="https://cdn.datatables.net/colreorder/2.1.1/js/colReorder.bootstrap5.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>