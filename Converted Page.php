<?php
/**
 * LottoExpert.net — SKAI Digit Frequency Analysis (Pick 5 / Daily 5)
 * Joomla 5.x + PHP 8.1+  |  UTF-8  |  ES5  |  Sorcerer-safe
 *
 * Structure: SKAI World-Class Results Intelligence layout (reference clone)
 * Logic:     Pick 5 per-position digit frequency (digits 0–9 per position)
 *
 * Requires upstream resolver to set:
 *   $gId, $dbCol, $stateName, $gName, $stateAbrev
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;

$app   = Factory::getApplication();
$doc   = Factory::getDocument();
$input = $app->input;
$db    = Factory::getDbo();
$user  = Factory::getUser();

/**
 * SEO: canonical + hreflang
 */
$uri              = Uri::getInstance();
$canonicalNoQuery = $uri->toString(['scheme', 'host', 'port', 'path']);
$pathWithQuery    = (string) $uri->toString(['path', 'query']);
$fullHref         = 'https://lottoexpert.net' . $pathWithQuery;

$doc->addCustomTag('<link rel="canonical" href="' . htmlspecialchars($canonicalNoQuery, ENT_QUOTES, 'UTF-8') . '" />');
$doc->addCustomTag('<link rel="alternate" hreflang="en" href="' . htmlspecialchars($fullHref, ENT_QUOTES, 'UTF-8') . '" />');
$doc->addCustomTag('<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($fullHref, ENT_QUOTES, 'UTF-8') . '" />');

/**
 * User context
 */
$loginStatus = (int) $user->guest;
$userPhone   = 'NULL';

if ($loginStatus === 1) {
    $leDfSess    = Factory::getSession();
    $userSession = (string) $leDfSess->getId();
} else {
    $userId = (int) $user->get('id');

    $qProfile = $db->getQuery(true)
        ->select($db->quoteName('profile_value'))
        ->from($db->quoteName('#__user_profiles'))
        ->where($db->quoteName('profile_key') . ' = ' . $db->quote('profile.phone'))
        ->where($db->quoteName('user_id') . ' = :userId');

    $qProfile->bind(':userId', $userId, ParameterType::INTEGER);
    $db->setQuery($qProfile);
    $tmp = (string) $db->loadResult();

    if ($tmp !== '') {
        $userPhone = str_replace(['"', '(', ')'], ['', '', '-'], $tmp);
    }
}

/**
 * Upstream resolver dependency
 */
$gId        = isset($gId)        ? (string) $gId        : '';
$dbCol      = isset($dbCol)      ? (string) $dbCol      : '';
$stateName  = isset($stateName)  ? (string) $stateName  : '';
$gName      = isset($gName)      ? (string) $gName      : '';
$stateAbrev = isset($stateAbrev) ? (string) $stateAbrev : '';

if ($gId === '' || $dbCol === '' || $stateName === '' || $gName === '' || $stateAbrev === '') {
    echo '<div style="padding:16px;border:1px solid #ccc;border-radius:10px;background:#fff;">';
    echo 'Configuration missing (gId/dbCol/stateName/gName/stateAbrev). This SKAI Pick 5 Digit Frequency block requires the upstream resolver.';
    echo '</div>';
    return;
}

/**
 * Title and meta
 */
$doc->setTitle('Digit Frequency Analysis — ' . $stateName . ' ' . $gName . ' | LottoExpert.net');
$doc->setMetaData('description', 'How often each digit (0–9) appears in each draw position for ' . $stateName . ' ' . $gName . '. Frequency analysis by position — descriptive statistics, not a prediction.');

/**
 * AI / SKAI href routing — exact overrides preserved from target
 */
$aiHref = '';
if ($gId === 'DCF') {
    $aiHref = '/picking-winning-numbers/artificial-intelligence/ai-lottery-predictions-for-dc-5-evening';
} elseif ($gId === 'DCE') {
    $aiHref = '/picking-winning-numbers/artificial-intelligence/ai-lottery-predictions-for-dc-5-midday';
} elseif ($gId === 'GAF') {
    $aiHref = '/picking-winning-numbers/artificial-intelligence/ai-lottery-predictions-for-georgia-georgia-five-evening';
} elseif ($gId === 'GAE') {
    $aiHref = '/picking-winning-numbers/artificial-intelligence/ai-lottery-predictions-for-georgia-georgia-five-midday';
} elseif ($gId === 'LAC') {
    $aiHref = '/picking-winning-numbers/artificial-intelligence/ai-lottery-predictions-for-louisiana-pick-5';
} elseif ($gId === 'MDF') {
    $aiHref = '/picking-winning-numbers/artificial-intelligence/ai-lottery-predictions-for-maryland-pick-5-evening';
} elseif ($gId === 'MDE') {
    $aiHref = '/picking-winning-numbers/artificial-intelligence/ai-lottery-predictions-for-maryland-pick-5-midday';
} elseif ($gId === 'OHG') {
    $aiHref = '/picking-winning-numbers/artificial-intelligence/ai-lottery-predictions-for-ohio-pick-5-evening';
} elseif ($gId === 'OHF') {
    $aiHref = '/picking-winning-numbers/artificial-intelligence/ai-lottery-predictions-for-ohio-pick-5-midday';
} else {
    $aiHref = '/picking-winning-numbers/artificial-intelligence/skai-lottery-prediction?gameId=' . rawurlencode($gId);
}

/**
 * Logo resolution
 */
$root      = rtrim((string) Uri::root(), '/');
$stateCode = strtolower($stateAbrev);

$leDfSlugify = static function (string $s): string {
    $s = strtolower(trim($s));
    $s = (string) preg_replace('/&amp;|&/i', ' and ', $s);
    $s = (string) preg_replace('/[^a-z0-9]+/i', '-', $s);
    $s = (string) preg_replace('/-+/', '-', $s);
    return trim($s, '-');
};

$lotterySlug = $leDfSlugify($gName);
$logoUrl     = '';
$logoExist   = false;

if ($stateCode !== '' && $lotterySlug !== '') {
    $logoUrl  = $root . '/images/lottodb/us/' . $stateCode . '/' . $lotterySlug . '.png';
    $relative = ltrim((string) str_replace($root, '', $logoUrl), '/');
    $logoPath = JPATH_ROOT . '/' . $relative;

    if (is_file($logoPath)) {
        $logoExist = true;
    }
}

/**
 * DataTables assets
 */
$doc->addStyleSheet('https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css');
$doc->addScript('https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js', ['version' => 'auto'], ['defer' => true]);
$doc->addScript('https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js', ['version' => 'auto'], ['defer' => true]);

/**
 * --------------------------------------------------------------------------
 * Helper functions  (leDf prefix = lotto digit frequency)
 * --------------------------------------------------------------------------
 */

function leDfFmtDateLong(?string $date): string
{
    if (!$date || $date === '') {
        return '—';
    }

    $ts = strtotime($date);

    return ($ts === false) ? '—' : date('F j, Y', $ts);
}

function leDfPad2(string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (ctype_digit($value) && (int) $value < 10) {
        return str_pad($value, 2, '0', STR_PAD_LEFT);
    }

    return $value;
}

function leDfCommaList(array $items): string
{
    $items = array_values(array_filter(array_map('trim', $items), static function ($v) {
        return $v !== '';
    }));

    if (empty($items)) {
        return '—';
    }

    return implode(', ', $items);
}

function leDfGetDigitFrequencies(
    \Joomla\Database\DatabaseDriver $db,
    string $dbCol,
    string $position,
    int $drawRange,
    string $gameId
): array {
    $query = $db->getQuery(true)
        ->select($db->quoteName($position))
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = :gameId')
        ->order($db->quoteName('draw_date') . ' DESC')
        ->setLimit($drawRange);

    $query->bind(':gameId', $gameId, ParameterType::STRING);
    $db->setQuery($query);

    $col = $db->loadColumn();

    if (!is_array($col)) {
        return [];
    }

    $vals = [];

    foreach ($col as $v) {
        $vals[] = (string) $v;
    }

    return array_count_values($vals);
}

function leDfGetLastDrawnAndCount(
    \Joomla\Database\DatabaseDriver $db,
    string $dbCol,
    int $digit,
    string $position,
    string $gameId,
    int $drawRange
): string {
    $query = $db->getQuery(true)
        ->select('MAX(' . $db->quoteName('draw_date') . ')')
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName($position) . ' = :digit')
        ->where($db->quoteName('game_id') . ' = :gameId');

    $query->bind(':digit', $digit, ParameterType::INTEGER);
    $query->bind(':gameId', $gameId, ParameterType::STRING);
    $db->setQuery($query);

    $lastDrawDate = (string) $db->loadResult();

    if ($lastDrawDate !== '') {
        $q2 = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($dbCol))
            ->where($db->quoteName('draw_date') . ' > ' . $db->quote($lastDrawDate))
            ->where($db->quoteName('game_id') . ' = :gameId')
            ->where($db->quoteName('draw_date') . ' <= NOW()');

        $q2->bind(':gameId', $gameId, ParameterType::STRING);
        $db->setQuery($q2);

        $n = (string) $db->loadResult();

        if ($n === '0') {
            return 'In last drw';
        }

        return $n . ' drws ago';
    }

    return 'Not in last ' . (int) $drawRange . ' dr.';
}

function leDfGetOverallFrequencies(
    \Joomla\Database\DatabaseDriver $db,
    string $dbCol,
    int $drawRange,
    string $gameId
): array {
    $query = $db->getQuery(true)
        ->select($db->quoteName(['first', 'second', 'third', 'fourth', 'fifth']))
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = :gameId')
        ->order($db->quoteName('draw_date') . ' DESC')
        ->setLimit($drawRange);

    $query->bind(':gameId', $gameId, ParameterType::STRING);
    $db->setQuery($query);

    $results = $db->loadObjectList();

    if (!is_array($results)) {
        return [];
    }

    $frequencies = [];

    foreach ($results as $r) {
        $n = (string) $r->first . (string) $r->second . (string) $r->third . (string) $r->fourth . (string) $r->fifth;

        if (!isset($frequencies[$n])) {
            $frequencies[$n] = 0;
        }

        $frequencies[$n]++;
    }

    arsort($frequencies);

    return $frequencies;
}

function leDfGetDrawingsAgo(
    \Joomla\Database\DatabaseDriver $db,
    string $dbCol,
    string $fiveDigitNumber,
    string $gameId,
    int $drawRange
): string {
    $query = $db->getQuery(true)
        ->select('MAX(' . $db->quoteName('draw_date') . ')')
        ->from($db->quoteName($dbCol))
        ->where(
            'CONCAT('
            . $db->quoteName('first') . ', '
            . $db->quoteName('second') . ', '
            . $db->quoteName('third') . ', '
            . $db->quoteName('fourth') . ', '
            . $db->quoteName('fifth')
            . ') = :num'
        )
        ->where($db->quoteName('game_id') . ' = :gameId');

    $query->bind(':num', $fiveDigitNumber, ParameterType::STRING);
    $query->bind(':gameId', $gameId, ParameterType::STRING);
    $db->setQuery($query);

    $lastDrawDate = (string) $db->loadResult();

    if ($lastDrawDate !== '') {
        $q2 = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($dbCol))
            ->where($db->quoteName('draw_date') . ' > ' . $db->quote($lastDrawDate))
            ->where($db->quoteName('game_id') . ' = :gameId');

        $q2->bind(':gameId', $gameId, ParameterType::STRING);
        $db->setQuery($q2);

        $n = (string) $db->loadResult();

        if ($n === '0') {
            return 'In last drw';
        }

        return $n . ' drws ago';
    }

    return 'Not in last ' . (int) $drawRange . ' dr.';
}

function leDfGetLatestResult(
    \Joomla\Database\DatabaseDriver $db,
    string $dbCol,
    string $gameId
): ?object {
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = :gameId')
        ->order($db->quoteName('draw_date') . ' DESC')
        ->setLimit(1);

    $query->bind(':gameId', $gameId, ParameterType::STRING);
    $db->setQuery($query);

    $result = $db->loadObject();

    return is_object($result) ? $result : null;
}

function leDfRenderPositionTable(
    \Joomla\Database\DatabaseDriver $db,
    string $dbCol,
    string $position,
    int $drawRange,
    string $gameId,
    string $title,
    string $tableId
): void {
    $gradMap = [
        'first'  => 'skai-card-head--horizon',
        'second' => 'skai-card-head--radiant',
        'third'  => 'skai-card-head--success',
        'fourth' => 'skai-card-head--ember',
        'fifth'  => 'skai-card-head--horizon',
    ];

    $gradClass = isset($gradMap[$position]) ? $gradMap[$position] : 'skai-card-head--horizon';

    echo '<div class="skai-card" style="margin-bottom:14px;">';
    echo '<div class="skai-card-head ' . htmlspecialchars($gradClass, ENT_QUOTES, 'UTF-8') . '">';
    echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo '<span class="skai-card-sub">Digits 0–9 in last ' . (int) $drawRange . ' draws</span>';
    echo '</div>';
    echo '<div class="skai-table-wrap">';
    echo '<table id="' . htmlspecialchars($tableId, ENT_QUOTES, 'UTF-8') . '" class="skai-table">';
    echo '<thead><tr><th>Digit</th><th>Drawn Times</th><th>Last Drawn</th></tr></thead><tbody>';

    $digitFrequencies = leDfGetDigitFrequencies($db, $dbCol, $position, $drawRange, $gameId);

    for ($num = 0; $num <= 9; $num++) {
        $key        = (string) $num;
        $frequency  = isset($digitFrequencies[$key]) ? (int) $digitFrequencies[$key] : 0;
        $drawsSince = leDfGetLastDrawnAndCount($db, $dbCol, $num, $position, $gameId, $drawRange);

        echo '<tr>';
        echo '<td><span class="skai-pill skai-pill--main">' . (int) $num . '</span></td>';
        echo '<td>' . (int) $frequency . ' ×</td>';
        echo '<td class="skai-drawings-ago">' . htmlspecialchars($drawsSince, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div></div>';
}

/**
 * --------------------------------------------------------------------------
 * Total drawings + draw range (POST)
 * --------------------------------------------------------------------------
 */
$qCount = $db->getQuery(true)
    ->select('COUNT(*)')
    ->from($db->quoteName($dbCol))
    ->where($db->quoteName('game_id') . ' = :gameId');

$qCount->bind(':gameId', $gId, ParameterType::STRING);
$db->setQuery($qCount);
$totalDrawings = (int) $db->loadResult();

if ($totalDrawings < 1) {
    echo '<div style="padding:16px;border:1px solid #ccc;border-radius:10px;background:#fff;">No draw history found for this game.</div>';
    return;
}

$postedDrawRange = (int) $input->post->getInt('drawRange', 100);
$minRange        = 10;
$maxRange        = max($minRange, $totalDrawings);
$drawRange       = max($minRange, min($maxRange, $postedDrawRange));

/**
 * --------------------------------------------------------------------------
 * Latest result
 * --------------------------------------------------------------------------
 */
$latestResult = leDfGetLatestResult($db, $dbCol, $gId);

$draw_date      = '';
$next_draw_date = '';
$next_jackpot   = '';
$p1 = $p2 = $p3 = $p4 = $p5 = '';

if ($latestResult) {
    $draw_date      = isset($latestResult->draw_date)      ? (string) $latestResult->draw_date      : '';
    $next_draw_date = isset($latestResult->next_draw_date) ? (string) $latestResult->next_draw_date : '';
    $next_jackpot   = isset($latestResult->next_jackpot)   ? (string) $latestResult->next_jackpot   : '';
    $p1             = isset($latestResult->first)          ? (string) $latestResult->first           : '';
    $p2             = isset($latestResult->second)         ? (string) $latestResult->second          : '';
    $p3             = isset($latestResult->third)          ? (string) $latestResult->third           : '';
    $p4             = isset($latestResult->fourth)         ? (string) $latestResult->fourth          : '';
    $p5             = isset($latestResult->fifth)          ? (string) $latestResult->fifth           : '';
}

/**
 * --------------------------------------------------------------------------
 * Game structure detection
 * Pick 5: 5 draw positions, digits 0–9 per position, no bonus ball
 * This page intelligently derives position count from the game's column map.
 * --------------------------------------------------------------------------
 */
$gamePositions = [
    'first'  => 'First Position',
    'second' => 'Second Position',
    'third'  => 'Third Position',
    'fourth' => 'Fourth Position',
    'fifth'  => 'Fifth Position',
];

$positionCount = count($gamePositions);
$hasBonus      = false;

/**
 * --------------------------------------------------------------------------
 * Compute all position frequencies (cached to avoid duplicate queries)
 * --------------------------------------------------------------------------
 */
$allPositionFreqs = [];

foreach (array_keys($gamePositions) as $posKey) {
    $rawFreq = leDfGetDigitFrequencies($db, $dbCol, $posKey, $drawRange, $gId);
    $posFreq = [];

    for ($d = 0; $d <= 9; $d++) {
        $k           = (string) $d;
        $posFreq[$k] = isset($rawFreq[$k]) ? (int) $rawFreq[$k] : 0;
    }

    $allPositionFreqs[$posKey] = $posFreq;
}

/**
 * --------------------------------------------------------------------------
 * Aggregate digit frequency across all positions (0–9)
 * --------------------------------------------------------------------------
 */
$aggDigitFreq = [];

for ($d = 0; $d <= 9; $d++) {
    $aggDigitFreq[(string) $d] = 0;
}

foreach ($allPositionFreqs as $pf) {
    foreach ($pf as $k => $cnt) {
        $aggDigitFreq[$k] += $cnt;
    }
}

$sortedDesc = $aggDigitFreq;
arsort($sortedDesc, SORT_NUMERIC);
$topActiveDigitKeys   = array_slice(array_keys($sortedDesc), 0, 5);
$topActiveDigitValues = [];

foreach ($topActiveDigitKeys as $k) {
    $topActiveDigitValues[] = (int) $aggDigitFreq[$k];
}

$sortedAsc = $aggDigitFreq;
asort($sortedAsc, SORT_NUMERIC);
$quietDigitKeys   = array_slice(array_keys($sortedAsc), 0, 5);
$quietDigitValues = [];

foreach ($quietDigitKeys as $k) {
    $quietDigitValues[] = (int) $aggDigitFreq[$k];
}

$allDigitChartLabels = [];
$allDigitChartValues = [];

for ($d = 0; $d <= 9; $d++) {
    $allDigitChartLabels[] = (string) $d;
    $allDigitChartValues[] = (int) $aggDigitFreq[(string) $d];
}

/**
 * --------------------------------------------------------------------------
 * Latest draw context — per-position digit analysis
 * --------------------------------------------------------------------------
 */
$latestDrawContext = [];

if ($latestResult) {
    $latestDigits = [(string) $p1, (string) $p2, (string) $p3, (string) $p4, (string) $p5];
    $posKeys      = array_keys($gamePositions);

    foreach ($posKeys as $idx => $posKey) {
        $digit = trim($latestDigits[$idx] ?? '');

        if ($digit === '') {
            continue;
        }

        $digitInt   = (int) $digit;
        $freq       = (int) ($allPositionFreqs[$posKey][(string) $digitInt] ?? 0);
        $drawsSince = leDfGetLastDrawnAndCount($db, $dbCol, $digitInt, $posKey, $gId, $drawRange);

        $latestDrawContext[] = [
            'position'   => $gamePositions[$posKey],
            'digit'      => $digit,
            'freq'       => $freq,
            'drawsSince' => $drawsSince,
        ];
    }
}

/**
 * --------------------------------------------------------------------------
 * Overall combinations (top 10 most drawn Pick-5 results)
 * --------------------------------------------------------------------------
 */
$overall = leDfGetOverallFrequencies($db, $dbCol, $drawRange, $gId);

/**
 * --------------------------------------------------------------------------
 * Summary copy
 * --------------------------------------------------------------------------
 */
$topDigitSummaryStr   = leDfCommaList(array_slice($topActiveDigitKeys, 0, 3));
$quietDigitSummaryStr = leDfCommaList(array_slice($quietDigitKeys, 0, 3));
$heroInsight          = 'Per-position digit distribution across the last ' . (int) $drawRange . ' draws of ' . $stateName . ' ' . $gName . '. Each position tracks how often digits 0–9 appear — descriptive analysis, not a prediction.';
$overviewNote         = 'Frequency shows historical occurrence within the selected window. It can help identify recent concentration and quiet periods, but it should be interpreted as context rather than prediction.';

/**
 * --------------------------------------------------------------------------
 * Structured data (JSON-LD)
 * --------------------------------------------------------------------------
 */
$jsonLd = [
    '@context'    => 'https://schema.org',
    '@type'       => 'WebPage',
    'name'        => 'Digit Frequency Analysis — ' . $stateName . ' ' . $gName,
    'description' => 'Per-position digit frequency analysis for ' . $stateName . ' ' . $gName . '. See how often digits 0–9 appear in each of the ' . $positionCount . ' draw positions.',
    'url'         => htmlspecialchars($canonicalNoQuery, ENT_QUOTES, 'UTF-8'),
];

$jsonLdEncoded = json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

?>
<style>
:root{
  --skai-blue:#1C66FF;
  --deep-navy:#0A1A33;
  --sky-gray:#EFEFF5;
  --soft-slate:#7F8DAA;
  --success-green:#20C997;
  --caution-amber:#F5A623;
  --white:#FFFFFF;
  --danger-red:#A61D2D;

  --grad-horizon:linear-gradient(135deg, #0A1A33 0%, #1C66FF 100%);
  --grad-radiant:linear-gradient(135deg, #1C66FF 0%, #7F8DAA 100%);
  --grad-slate:linear-gradient(180deg, #EFEFF5 0%, #FFFFFF 100%);
  --grad-success:linear-gradient(135deg, #20C997 0%, #0A1A33 100%);
  --grad-ember:linear-gradient(135deg, #F5A623 0%, #0A1A33 100%);

  --text:#0A1A33;
  --text-soft:#5F6F8C;
  --line:rgba(10,26,51,.10);
  --line-strong:rgba(10,26,51,.16);
  --shadow-1:0 12px 32px rgba(10,26,51,.08);
  --shadow-2:0 20px 48px rgba(10,26,51,.14);
  --radius-14:14px;
  --radius-18:18px;
  --radius-22:22px;
  --font:Inter,"SF Pro Text","SF Pro Display","Helvetica Neue",Arial,sans-serif;
}

*{box-sizing:border-box}

.ledf-page{
  max-width:1180px;
  margin:0 auto;
  padding:20px 14px 32px;
  color:var(--text);
  font-family:var(--font);
}

.ledf-page a{
  text-decoration:none;
}

.skai-grid{
  display:grid;
  gap:14px;
}

/* ── Hero ───────────────────────────────────────────────────────────────── */
.skai-hero{
  position:relative;
  overflow:hidden;
  border-radius:var(--radius-22);
  background:
    radial-gradient(900px 420px at -10% -20%, rgba(255,255,255,.13) 0%, rgba(255,255,255,0) 55%),
    radial-gradient(780px 340px at 110% 0%, rgba(255,255,255,.10) 0%, rgba(255,255,255,0) 55%),
    var(--grad-horizon);
  color:#fff;
  box-shadow:var(--shadow-2);
  border:1px solid rgba(255,255,255,.10);
}

.skai-hero-inner{
  padding:22px 20px 18px;
}

.skai-hero-top{
  display:grid;
  grid-template-columns:110px minmax(0,1fr) 280px;
  gap:18px;
  align-items:start;
}

.skai-logo{
  width:110px;
  height:110px;
  border-radius:20px;
  background:rgba(255,255,255,.94);
  display:flex;
  align-items:center;
  justify-content:center;
  box-shadow:0 14px 30px rgba(0,0,0,.16);
  overflow:hidden;
  padding:12px;
}

.skai-logo img{
  width:100%;
  height:100%;
  object-fit:contain;
  display:block;
}

.skai-hero-copy{
  min-width:0;
}

.skai-kicker{
  font-size:12px;
  line-height:1.2;
  letter-spacing:.18em;
  text-transform:uppercase;
  font-weight:800;
  color:rgba(255,255,255,.76);
  margin:2px 0 8px;
}

.skai-title{
  margin:0;
  font-size:30px;
  line-height:1.08;
  font-weight:900;
  letter-spacing:-.02em;
  color:#fff;
}

.skai-hero-summary{
  margin:12px 0 0;
  max-width:68ch;
  font-size:15px;
  line-height:1.65;
  color:rgba(255,255,255,.90);
}

.skai-result-panel{
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.14);
  border-radius:18px;
  padding:14px;
  backdrop-filter:blur(4px);
}

.skai-panel-label{
  font-size:11px;
  line-height:1.2;
  font-weight:800;
  letter-spacing:.14em;
  text-transform:uppercase;
  color:rgba(255,255,255,.72);
  margin:0 0 10px;
}

.skai-meta-stack{
  display:grid;
  gap:10px;
}

.skai-meta-row{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
}

.skai-meta-box{
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.10);
  border-radius:14px;
  padding:10px;
}

.skai-meta-box span{
  display:block;
}

.skai-meta-box .label{
  font-size:11px;
  line-height:1.2;
  font-weight:800;
  letter-spacing:.08em;
  text-transform:uppercase;
  color:rgba(255,255,255,.70);
}

.skai-meta-box .value{
  margin-top:6px;
  font-size:15px;
  line-height:1.35;
  font-weight:850;
  color:#fff;
}

.skai-ball-row{
  display:flex;
  align-items:center;
  flex-wrap:wrap;
  gap:8px;
  margin-top:16px;
}

.skai-ball{
  width:42px;
  height:42px;
  border-radius:999px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:16px;
  font-weight:900;
  letter-spacing:.02em;
  position:relative;
}

.skai-ball--main{
  background:linear-gradient(180deg, #FFFFFF 0%, #F3F6FF 100%);
  color:var(--deep-navy);
  border:1px solid rgba(10,26,51,.14);
  box-shadow:0 10px 20px rgba(10,26,51,.12), inset 0 1px 0 rgba(255,255,255,.90);
}

.skai-hero-actions{
  margin-top:18px;
  display:grid;
  grid-template-columns:1.2fr 1fr 1fr;
  gap:10px;
}

.skai-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  border-radius:14px;
  min-height:48px;
  padding:12px 16px;
  font-size:14px;
  line-height:1.2;
  font-weight:850;
  transition:transform .14s ease, box-shadow .14s ease, filter .14s ease;
}

.skai-btn:hover{
  transform:translateY(-1px);
}

.skai-btn:focus,
.skai-btn:focus-visible{
  outline:3px solid rgba(255,255,255,.30);
  outline-offset:3px;
}

.skai-btn--primary{
  background:#fff;
  color:var(--deep-navy);
  box-shadow:0 12px 22px rgba(0,0,0,.14);
}

.skai-btn--secondary{
  background:rgba(255,255,255,.12);
  color:#fff;
  border:1px solid rgba(255,255,255,.18);
}

.skai-advanced-links{
  display:grid;
  grid-template-columns:repeat(3, minmax(0,1fr));
  gap:10px;
  margin-top:10px;
}

.skai-mini-link{
  display:flex;
  align-items:center;
  justify-content:center;
  text-align:center;
  min-height:44px;
  padding:10px 12px;
  border-radius:12px;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.10);
  color:#fff;
  font-size:13px;
  line-height:1.3;
  font-weight:800;
}

/* ── Stats strip ─────────────────────────────────────────────────────────── */
.skai-strip{
  margin-top:14px;
  display:grid;
  grid-template-columns:repeat(4, minmax(0,1fr));
  gap:14px;
}

.skai-stat{
  border-radius:18px;
  overflow:hidden;
  background:var(--grad-slate);
  border:1px solid var(--line);
  box-shadow:var(--shadow-1);
}

.skai-stat-head{
  padding:12px 14px;
  color:#fff;
  font-size:12px;
  line-height:1.25;
  letter-spacing:.12em;
  text-transform:uppercase;
  font-weight:850;
}

.skai-stat-head--horizon{background:var(--grad-horizon)}
.skai-stat-head--radiant{background:var(--grad-radiant)}
.skai-stat-head--success{background:var(--grad-success)}
.skai-stat-head--ember{background:var(--grad-ember)}

.skai-stat-body{
  padding:14px;
  min-height:120px;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
}

.skai-stat-value{
  font-size:24px;
  line-height:1.12;
  font-weight:900;
  letter-spacing:-.02em;
  color:var(--deep-navy);
}

.skai-stat-note{
  margin-top:10px;
  font-size:13px;
  line-height:1.6;
  color:var(--text-soft);
}

/* ── Tabs ─────────────────────────────────────────────────────────────────── */
.skai-tabs{
  margin-top:18px;
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  padding:6px;
  border-radius:999px;
  background:var(--sky-gray);
  border:1px solid var(--line);
}

.skai-tab{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:42px;
  padding:10px 16px;
  border-radius:999px;
  color:var(--deep-navy);
  font-size:13px;
  line-height:1.2;
  font-weight:850;
}

.skai-tab--active{
  background:var(--grad-horizon);
  color:#fff;
  box-shadow:0 10px 20px rgba(10,26,51,.12);
}

/* ── Sections ─────────────────────────────────────────────────────────────── */
.skai-section{
  margin-top:16px;
  background:var(--grad-slate);
  border:1px solid var(--line);
  border-radius:20px;
  box-shadow:var(--shadow-1);
  overflow:hidden;
}

.skai-section-head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:14px;
  padding:18px 18px 14px;
  border-bottom:1px solid var(--line);
  background:rgba(255,255,255,.55);
}

.skai-section-title{
  margin:0;
  font-size:22px;
  line-height:1.15;
  letter-spacing:-.02em;
  font-weight:900;
  color:var(--deep-navy);
}

.skai-section-sub{
  margin:8px 0 0;
  max-width:76ch;
  font-size:14px;
  line-height:1.65;
  color:var(--text-soft);
}

.skai-section-body{
  padding:16px 18px 18px;
  background:#fff;
}

/* ── Overview grid ───────────────────────────────────────────────────────── */
.skai-overview-grid{
  display:grid;
  grid-template-columns:1.2fr 1fr;
  gap:14px;
}

.skai-overview-grid > *{
  min-width:0;
}

/* ── Card ─────────────────────────────────────────────────────────────────── */
.skai-card{
  background:#fff;
  border:1px solid var(--line);
  border-radius:18px;
  box-shadow:0 10px 24px rgba(10,26,51,.06);
  overflow:hidden;
}

.skai-card-head{
  padding:14px 16px;
  color:#fff;
  font-weight:850;
  font-size:16px;
  line-height:1.25;
}

.skai-card-head--horizon{background:var(--grad-horizon)}
.skai-card-head--radiant{background:var(--grad-radiant)}
.skai-card-head--success{background:var(--grad-success)}
.skai-card-head--ember{background:var(--grad-ember)}

.skai-card-sub{
  display:block;
  margin-top:4px;
  font-size:12px;
  line-height:1.45;
  font-weight:700;
  opacity:.92;
}

.skai-card-body{
  padding:14px 16px 16px;
}

/* ── Charts ──────────────────────────────────────────────────────────────── */
.skai-chart-wrap{
  width:100%;
  overflow:hidden;
  box-sizing:border-box;
}

.skai-chart-frame{
  position:relative;
  width:100%;
  height:300px;
  overflow:hidden;
}

.skai-chart-frame canvas{
  display:block;
  width:100% !important;
  max-width:100%;
}

/* ── Note ─────────────────────────────────────────────────────────────────── */
.skai-note{
  margin-top:14px;
  padding:14px 16px;
  border-radius:16px;
  background:linear-gradient(180deg, #F8FAFE 0%, #FFFFFF 100%);
  border:1px solid var(--line);
  color:var(--text-soft);
  font-size:13px;
  line-height:1.7;
}

/* ── Two-column ──────────────────────────────────────────────────────────── */
.skai-two-col{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:14px;
}

.skai-two-col > *{
  min-width:0;
}

/* ── Draw context list ───────────────────────────────────────────────────── */
.skai-history-list{
  display:grid;
  gap:10px;
}

.skai-history-item{
  display:grid;
  grid-template-columns:160px 1fr auto;
  gap:12px;
  align-items:center;
  padding:12px 14px;
  border-radius:14px;
  border:1px solid var(--line);
  background:linear-gradient(180deg, #FFFFFF 0%, #FAFBFF 100%);
}

.skai-history-name{
  font-size:14px;
  line-height:1.35;
  font-weight:850;
  color:var(--deep-navy);
}

.skai-history-date{
  font-size:13px;
  line-height:1.55;
  color:var(--text-soft);
}

.skai-history-badge{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:110px;
  min-height:36px;
  padding:8px 12px;
  border-radius:999px;
  background:var(--grad-radiant);
  color:#fff;
  font-size:12px;
  line-height:1.2;
  font-weight:850;
}

/* ── Controls ─────────────────────────────────────────────────────────────── */
.skai-controls{
  padding:14px 16px;
  border-bottom:1px solid var(--line);
  background:rgba(255,255,255,.76);
}

.skai-controls form{
  margin:0;
}

.skai-controls-row{
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  justify-content:space-between;
  gap:12px;
}

.skai-controls-left{
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  gap:10px;
}

.skai-controls-right{
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  gap:10px;
}

.skai-controls label{
  font-size:13px;
  line-height:1.2;
  font-weight:850;
  color:var(--deep-navy);
}

.skai-select{
  min-width:122px;
  min-height:44px;
  padding:10px 12px;
  border-radius:12px;
  border:1px solid var(--line-strong);
  background:#fff;
  color:var(--deep-navy);
  font-size:14px;
  line-height:1.2;
  font-weight:800;
}

.skai-button{
  min-height:44px;
  padding:10px 16px;
  border:none;
  border-radius:12px;
  background:var(--grad-horizon);
  color:#fff;
  font-size:13px;
  line-height:1.2;
  font-weight:850;
  cursor:pointer;
  box-shadow:0 10px 20px rgba(10,26,51,.12);
}

.skai-button:hover{
  filter:brightness(1.03);
}

.skai-button:focus,
.skai-select:focus{
  outline:3px solid rgba(28,102,255,.35);
  outline-offset:2px;
}

/* ── Filter buttons ───────────────────────────────────────────────────────── */
.skai-filter-group{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}

.skai-filter{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:36px;
  padding:8px 12px;
  border-radius:999px;
  border:1px solid var(--line);
  background:#fff;
  color:var(--deep-navy);
  font-size:12px;
  line-height:1.2;
  font-weight:800;
  cursor:pointer;
}

.skai-filter.is-active{
  background:var(--grad-horizon);
  border-color:transparent;
  color:#fff;
}

/* ── Table ────────────────────────────────────────────────────────────────── */
.skai-table-wrap{
  padding:16px;
  overflow-x:auto;
}

table.skai-table{
  width:100%;
  min-width:320px;
  border-collapse:separate;
  border-spacing:0;
  background:#fff;
  border:1px solid var(--line);
  border-radius:16px;
  overflow:hidden;
}

table.skai-table thead th{
  position:sticky;
  top:0;
  z-index:1;
  background:var(--grad-horizon);
  color:#fff;
  padding:8px 6px;
  font-size:11px;
  line-height:1.2;
  letter-spacing:.04em;
  text-transform:uppercase;
  font-weight:850;
  text-align:center;
  border-bottom:1px solid rgba(255,255,255,.12);
}

table.skai-table tbody td{
  padding:9px 7px;
  text-align:center;
  border-bottom:1px solid rgba(10,26,51,.06);
  font-size:14px;
  line-height:1.45;
  color:var(--deep-navy);
  vertical-align:middle;
}

table.skai-table tbody tr:hover{
  background:rgba(28,102,255,.04);
}

.skai-drawings-ago{
  color:var(--text-soft);
  font-weight:900;
  font-size:13px;
}

/* ── Pills ────────────────────────────────────────────────────────────────── */
.skai-pill{
  width:34px;
  height:34px;
  border-radius:999px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:14px;
  line-height:1;
  font-weight:900;
}

.skai-pill--main{
  background:linear-gradient(180deg, #FFFFFF 0%, #F3F6FF 100%);
  color:var(--deep-navy);
  border:1px solid rgba(10,26,51,.14);
  box-shadow:0 8px 16px rgba(10,26,51,.08);
}

/* ── Tracking ─────────────────────────────────────────────────────────────── */
.skai-checkbox{
  transform:scale(1.25);
  cursor:pointer;
}

.skai-tracked{
  margin-top:14px;
  border:1px solid var(--line);
  border-radius:16px;
  background:var(--grad-slate);
  overflow:hidden;
}

.skai-tracked-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  padding:12px 14px;
  border-bottom:1px solid var(--line);
}

.skai-tracked-title{
  margin:0;
  font-size:15px;
  line-height:1.2;
  font-weight:850;
  color:var(--deep-navy);
}

.skai-tracked-actions{
  display:flex;
  align-items:center;
  gap:8px;
}

.skai-link-btn{
  border:none;
  background:none;
  color:var(--skai-blue);
  font-size:12px;
  line-height:1.2;
  font-weight:850;
  cursor:pointer;
  padding:0;
}

.skai-chip-wrap{
  padding:12px 14px 14px;
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  min-height:64px;
  align-items:flex-start;
}

.skai-empty{
  font-size:13px;
  line-height:1.6;
  color:var(--text-soft);
}

.skai-chip{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:36px;
  padding:8px 12px;
  border-radius:999px;
  font-size:13px;
  line-height:1.2;
  font-weight:850;
  background:linear-gradient(180deg, #FFFFFF 0%, #F3F6FF 100%);
  border:1px solid rgba(10,26,51,.14);
  color:var(--deep-navy);
}

/* ── Tools ────────────────────────────────────────────────────────────────── */
.skai-tool-grid{
  display:grid;
  grid-template-columns:1.2fr 1fr 1fr;
  gap:14px;
}

.skai-tool{
  border-radius:18px;
  overflow:hidden;
  border:1px solid var(--line);
  background:#fff;
  box-shadow:0 10px 24px rgba(10,26,51,.06);
}

.skai-tool-head{
  padding:14px 16px;
  color:#fff;
  font-size:15px;
  line-height:1.3;
  font-weight:850;
}

.skai-tool-body{
  padding:15px 16px 16px;
}

.skai-tool-copy{
  margin:0 0 14px;
  font-size:14px;
  line-height:1.7;
  color:var(--text-soft);
}

.skai-tool-cta{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:44px;
  padding:10px 16px;
  border-radius:12px;
  font-size:13px;
  line-height:1.2;
  font-weight:850;
  background:var(--grad-horizon);
  color:#fff;
}

.skai-utility-grid{
  display:grid;
  grid-template-columns:repeat(4, minmax(0,1fr));
  gap:10px;
  margin-top:12px;
}

.skai-utility-link{
  min-height:42px;
  display:flex;
  align-items:center;
  justify-content:center;
  text-align:center;
  padding:10px 12px;
  border-radius:12px;
  border:1px solid var(--line);
  background:var(--grad-slate);
  color:var(--deep-navy);
  font-size:13px;
  line-height:1.3;
  font-weight:850;
}

/* ── Method note ──────────────────────────────────────────────────────────── */
.skai-method-note{
  padding:16px;
  border-radius:16px;
  border:1px solid var(--line);
  background:linear-gradient(180deg, #FAFBFF 0%, #FFFFFF 100%);
  font-size:14px;
  line-height:1.8;
  color:var(--text-soft);
}

.skai-method-note strong{
  color:var(--deep-navy);
}

/* ── Digit heatmap (target visual system) ────────────────────────────────── */
.ledf-position-card{
  margin:0 0 14px;
  padding:16px;
  border:1px solid var(--line);
  border-radius:16px;
  background:linear-gradient(180deg, rgba(239,239,245,.75) 0%, #FFFFFF 100%);
}

.ledf-position-title{
  margin:0 0 12px;
  font-size:14px;
  font-weight:900;
  color:var(--deep-navy);
  letter-spacing:.2px;
}

.ledf-digit-grid{
  display:grid;
  grid-template-columns:repeat(10, minmax(0,1fr));
  gap:10px;
}

.ledf-digit-cell{
  padding:10px 8px;
  border:1px solid rgba(10,26,51,.10);
  border-radius:14px;
  background:#fff;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  gap:8px;
  min-height:88px;
}

.ledf-ball{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:34px;
  height:34px;
  border-radius:999px;
  font-weight:800;
  font-size:14px;
  color:var(--deep-navy);
  background:radial-gradient(circle at 30%, #ffffff 0%, #e7e9f2 55%, #d7dbea 100%);
  border:1px solid rgba(10,26,51,.18);
  box-shadow:0 6px 14px rgba(10,26,51,.10);
}

.ledf-ball--high{
  background:radial-gradient(circle at 30%, #d9fff3 0%, #7fe9c9 50%, #20C997 100%);
  border-color:rgba(32,201,151,.55);
  color:#0A1A33;
}

.ledf-ball--medium{
  background:radial-gradient(circle at 30%, #fff5df 0%, #ffd08a 55%, #F5A623 100%);
  border-color:rgba(245,166,35,.55);
  color:#0A1A33;
}

.ledf-ball--low{
  background:radial-gradient(circle at 30%, #ffe3e3 0%, #ff9a9a 55%, #ff5c5c 100%);
  border-color:rgba(255,92,92,.55);
  color:#fff;
}

.ledf-count{
  font-size:12px;
  line-height:1.2;
  color:var(--text-soft);
  font-weight:900;
}

.ledf-bar{
  width:100%;
  height:6px;
  border-radius:999px;
  background:rgba(10,26,51,.10);
  overflow:hidden;
}

.ledf-bar > span{
  display:block;
  height:100%;
  width:0%;
  background:linear-gradient(135deg, #0A1A33 0%, #1C66FF 100%);
}

.ledf-legend{
  display:flex;
  flex-wrap:wrap;
  justify-content:center;
  gap:14px;
  margin-top:12px;
  color:var(--text-soft);
  font-size:13px;
}

.ledf-legend-dot{
  width:14px;
  height:14px;
  border-radius:999px;
  display:inline-block;
  border:1px solid rgba(10,26,51,.15);
  margin-right:8px;
  vertical-align:middle;
}

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width:1080px){
  .skai-hero-top{
    grid-template-columns:96px minmax(0,1fr);
  }

  .skai-result-panel{
    grid-column:1 / -1;
  }

  .skai-strip,
  .skai-tool-grid,
  .skai-overview-grid,
  .skai-two-col{
    grid-template-columns:1fr;
  }

  .skai-hero-actions,
  .skai-advanced-links{
    grid-template-columns:1fr;
  }

  .skai-utility-grid{
    grid-template-columns:repeat(2, minmax(0,1fr));
  }
}

@media (max-width:780px){
  .ledf-page{
    padding:14px 10px 24px;
  }

  .skai-title{
    font-size:26px;
  }

  .skai-section-head{
    padding:16px 14px 12px;
  }

  .skai-section-body{
    padding:14px;
  }

  .skai-strip{
    grid-template-columns:1fr 1fr;
  }

  .skai-history-item{
    grid-template-columns:1fr;
    align-items:start;
  }

  .skai-meta-row{
    grid-template-columns:1fr;
  }

  .skai-tabs{
    border-radius:18px;
  }

  .skai-utility-grid{
    grid-template-columns:1fr 1fr;
  }

  .ledf-digit-grid{
    grid-template-columns:repeat(5, minmax(0,1fr));
  }
}

@media (max-width:540px){
  .skai-strip{
    grid-template-columns:1fr;
  }
}

@media (prefers-reduced-motion: reduce){
  .skai-btn,
  .skai-button{
    transition:none;
  }
}
</style>

<?php if ($jsonLdEncoded !== false) : ?>
<script type="application/ld+json">
<?php echo $jsonLdEncoded; ?>
</script>
<?php endif; ?>

<div class="ledf-page" data-skaimodule="digit-frequency-pick5">

  <!-- ═══════════════════════════════════════════════════════════════════════
       HERO
       ═══════════════════════════════════════════════════════════════════════ -->
  <section class="skai-hero" aria-label="Digit frequency analysis header">
    <div class="skai-hero-inner">
      <div class="skai-hero-top">

        <div class="skai-logo" aria-hidden="<?php echo $logoExist ? 'false' : 'true'; ?>">
          <?php if ($logoExist && $logoUrl !== '') : ?>
            <img
              src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
              alt="<?php echo htmlspecialchars($stateName . ' ' . $gName, ENT_QUOTES, 'UTF-8'); ?>"
              width="110"
              height="110"
              loading="lazy"
              decoding="async"
            >
          <?php else : ?>
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M12 2l2.7 6.2L21 9l-4.7 4.1L17.6 21 12 17.8 6.4 21l1.3-7.9L3 9l6.3-.8L12 2z" stroke="rgba(10,26,51,.55)" stroke-width="1.6" stroke-linejoin="round"/>
            </svg>
          <?php endif; ?>
        </div>

        <div class="skai-hero-copy">
          <div class="skai-kicker">Digit Frequency &bull; Per-Position Analysis &bull; SKAI Analytical View</div>
          <h1 class="skai-title">
            <?php echo htmlspecialchars($stateName, ENT_QUOTES, 'UTF-8'); ?>
            &ndash;
            <?php echo htmlspecialchars($gName, ENT_QUOTES, 'UTF-8'); ?>
          </h1>
          <p class="skai-hero-summary"><?php echo htmlspecialchars($heroInsight, ENT_QUOTES, 'UTF-8'); ?></p>

          <?php if ($latestResult && ($p1 !== '' || $p2 !== '' || $p3 !== '' || $p4 !== '' || $p5 !== '')) : ?>
            <div class="skai-ball-row" aria-label="Latest draw digits">
              <?php if ($p1 !== '') : ?><span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p1, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
              <?php if ($p2 !== '') : ?><span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p2, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
              <?php if ($p3 !== '') : ?><span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p3, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
              <?php if ($p4 !== '') : ?><span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p4, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
              <?php if ($p5 !== '') : ?><span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p5, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="skai-hero-actions" aria-label="Primary actions">
            <a class="skai-btn skai-btn--primary" href="<?php echo htmlspecialchars($aiHref, ENT_QUOTES, 'UTF-8'); ?>">
              Open SKAI Analysis
            </a>
            <a class="skai-btn skai-btn--secondary" href="/picking-winning-numbers/artificial-intelligence/ai-powered-predictions?game_id=<?php echo rawurlencode($gId); ?>">
              AI Predictions
            </a>
            <a class="skai-btn skai-btn--secondary" href="#heatmap">
              View Digit Heatmap
            </a>
          </div>

          <div class="skai-advanced-links" aria-label="Advanced tools">
            <a class="skai-mini-link" href="/picking-winning-numbers/artificial-intelligence/skip-and-hit-analysis?game_id=<?php echo rawurlencode($gId); ?>">Skip &amp; Hit Analysis</a>
            <a class="skai-mini-link" href="/picking-winning-numbers/artificial-intelligence/markov-chain-monte-carlo-mcmc-analysis?game_id=<?php echo rawurlencode($gId); ?>">MCMC Markov Analysis</a>
            <a class="skai-mini-link" href="/all-lottery-heatmaps?gId=<?php echo rawurlencode($gId); ?>&amp;stateName=<?php echo rawurlencode($stateName); ?>&amp;gName=<?php echo rawurlencode($gName); ?>&amp;sTn=<?php echo rawurlencode(strtolower($stateAbrev)); ?>">Heatmap Analysis</a>
          </div>
        </div>

        <aside class="skai-result-panel" aria-label="Latest draw details">
          <div class="skai-panel-label">Latest draw summary</div>

          <div class="skai-meta-stack">
            <div class="skai-meta-row">
              <div class="skai-meta-box">
                <span class="label">Draw date</span>
                <span class="value"><?php echo htmlspecialchars(leDfFmtDateLong($draw_date), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <div class="skai-meta-box">
                <span class="label">Next draw date</span>
                <span class="value"><?php echo htmlspecialchars(leDfFmtDateLong($next_draw_date), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
            </div>

            <div class="skai-meta-box">
              <span class="label">Analysis window</span>
              <span class="value">Last <?php echo (int) $drawRange; ?> draws &bull; <?php echo (int) $positionCount; ?> positions tracked</span>
            </div>

            <?php if ($next_jackpot !== '' && $next_jackpot !== '0' && strtolower($next_jackpot) !== 'n/a' && (float) $next_jackpot > 0) : ?>
              <div class="skai-meta-box">
                <span class="label">Next jackpot</span>
                <span class="value">$<?php echo htmlspecialchars(number_format((float) $next_jackpot, 0, '.', ','), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
            <?php endif; ?>
          </div>
        </aside>

      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════════════════
       STATS STRIP
       ═══════════════════════════════════════════════════════════════════════ -->
  <section class="skai-strip" aria-label="Key takeaways">
    <article class="skai-stat">
      <div class="skai-stat-head skai-stat-head--horizon">Most active digits</div>
      <div class="skai-stat-body">
        <div class="skai-stat-value"><?php echo htmlspecialchars($topDigitSummaryStr, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="skai-stat-note">Highest aggregate appearance counts across all <?php echo (int) $positionCount; ?> positions in the current <?php echo (int) $drawRange; ?>-draw window.</div>
      </div>
    </article>

    <article class="skai-stat">
      <div class="skai-stat-head skai-stat-head--radiant">Quietest digits</div>
      <div class="skai-stat-body">
        <div class="skai-stat-value"><?php echo htmlspecialchars($quietDigitSummaryStr, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="skai-stat-note">Digits with the lowest aggregate frequency across all positions in the selected window.</div>
      </div>
    </article>

    <article class="skai-stat">
      <div class="skai-stat-head skai-stat-head--success">Latest draw</div>
      <div class="skai-stat-body">
        <div class="skai-stat-value"><?php echo htmlspecialchars($p1 . ' ' . $p2 . ' ' . $p3 . ' ' . $p4 . ' ' . $p5, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="skai-stat-note">Most recent verified draw result &mdash; <?php echo htmlspecialchars(leDfFmtDateLong($draw_date), ENT_QUOTES, 'UTF-8'); ?>.</div>
      </div>
    </article>

    <article class="skai-stat">
      <div class="skai-stat-head skai-stat-head--ember">Window analyzed</div>
      <div class="skai-stat-body">
        <div class="skai-stat-value"><?php echo (int) $drawRange; ?></div>
        <div class="skai-stat-note">Draw window currently loaded. <?php echo (int) $totalDrawings; ?> total draws available for this game.</div>
      </div>
    </article>
  </section>

  <!-- ═══════════════════════════════════════════════════════════════════════
       TAB NAVIGATION
       ═══════════════════════════════════════════════════════════════════════ -->
  <nav class="skai-tabs" aria-label="Page navigation">
    <a class="skai-tab skai-tab--active" href="#overview">Overview</a>
    <a class="skai-tab" href="#heatmap">Digit Heatmap</a>
    <a class="skai-tab" href="#draw-context">Draw Context</a>
    <a class="skai-tab" href="#tables">Tables</a>
    <a class="skai-tab" href="#tools">Advanced Tools</a>
  </nav>

  <!-- ═══════════════════════════════════════════════════════════════════════
       OVERVIEW SECTION
       ═══════════════════════════════════════════════════════════════════════ -->
  <section id="overview" class="skai-section" aria-labelledby="overview-title">
    <div class="skai-section-head">
      <div>
        <h2 id="overview-title" class="skai-section-title">Overview</h2>
        <p class="skai-section-sub">
          High-level view of digit activity across all <?php echo (int) $positionCount; ?> positions. Which digits appear most often? Which are quiet? This layer is optimized for fast orientation before moving into the per-position heatmap.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-overview-grid">
        <div class="skai-card">
          <div class="skai-card-head skai-card-head--horizon">
            Top active digits
            <span class="skai-card-sub">Highest aggregate frequency across all positions in last <?php echo (int) $drawRange; ?> draws</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-chart-wrap">
              <div class="skai-chart-frame">
                <canvas id="topActiveChart" aria-label="Top active digits chart" role="img"></canvas>
              </div>
            </div>
          </div>
        </div>

        <div class="skai-card">
          <div class="skai-card-head skai-card-head--ember">
            Quiet digits
            <span class="skai-card-sub">Lowest aggregate frequency across all positions</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-chart-wrap">
              <div class="skai-chart-frame">
                <canvas id="quietChart" aria-label="Quiet digits chart" role="img"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="skai-note">
        <?php echo htmlspecialchars($overviewNote, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════════════════
       DIGIT HEATMAP SECTION
       ═══════════════════════════════════════════════════════════════════════ -->
  <section id="heatmap" class="skai-section" aria-labelledby="heatmap-title">
    <div class="skai-section-head">
      <div>
        <h2 id="heatmap-title" class="skai-section-title">Digit distribution by position</h2>
        <p class="skai-section-sub">
          Each card below shows how digits 0–9 distributed in a specific draw position across the selected window. Color intensity signals relative frequency within that position: green = high, amber = medium, red = low. Use the sort control to reorder by frequency or by digit value.
        </p>
      </div>
    </div>

    <div class="skai-controls">
      <div class="skai-controls-row">
        <div class="skai-controls-left">
          <label for="heatmapSort">Sort digits by</label>
          <select id="heatmapSort" class="skai-select" aria-label="Sort heatmap cells">
            <option value="digit">Digit (0–9)</option>
            <option value="high">Most frequent</option>
            <option value="low">Least frequent</option>
          </select>
        </div>
        <div class="skai-controls-right">
          <div class="ledf-legend" aria-label="Frequency legend">
            <span><span class="ledf-legend-dot" style="background:radial-gradient(circle at 30%, #d9fff3 0%, #7fe9c9 50%, #20C997 100%);"></span>High</span>
            <span><span class="ledf-legend-dot" style="background:radial-gradient(circle at 30%, #fff5df 0%, #ffd08a 55%, #F5A623 100%);"></span>Medium</span>
            <span><span class="ledf-legend-dot" style="background:radial-gradient(circle at 30%, #ffe3e3 0%, #ff9a9a 55%, #ff5c5c 100%);"></span>Low</span>
          </div>
        </div>
      </div>
    </div>

    <div class="skai-section-body">

      <?php
      foreach ($gamePositions as $posKey => $posLabel) :
          $posFreq = $allPositionFreqs[$posKey] ?? [];

          $minFreq = PHP_INT_MAX;
          $maxFreq = PHP_INT_MIN;

          for ($d = 0; $d <= 9; $d++) {
              $cnt    = (int) ($posFreq[(string) $d] ?? 0);
              if ($cnt < $minFreq) { $minFreq = $cnt; }
              if ($cnt > $maxFreq) { $maxFreq = $cnt; }
          }

          if ($minFreq === PHP_INT_MAX) { $minFreq = 0; }
          if ($maxFreq === PHP_INT_MIN) { $maxFreq = 0; }

          $freqRange = max(1, $maxFreq - $minFreq);
      ?>
        <div class="ledf-position-card" data-position="<?php echo htmlspecialchars($posKey, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="ledf-position-title"><?php echo htmlspecialchars($posLabel, ENT_QUOTES, 'UTF-8'); ?></div>

          <div class="ledf-digit-grid" data-sortgrid="1">
            <?php for ($d = 0; $d <= 9; $d++) :
                $count      = (int) ($posFreq[(string) $d] ?? 0);
                $normalized = ($count - $minFreq) / $freqRange;

                $ballClass = 'ledf-ball--low';
                if ($normalized >= 0.67) {
                    $ballClass = 'ledf-ball--high';
                } elseif ($normalized >= 0.33) {
                    $ballClass = 'ledf-ball--medium';
                }

                $barWidth = (int) round($normalized * 100);
            ?>
              <div
                class="ledf-digit-cell"
                aria-label="Digit <?php echo (int) $d; ?> frequency in <?php echo htmlspecialchars($posLabel, ENT_QUOTES, 'UTF-8'); ?>"
                data-digit="<?php echo (int) $d; ?>"
                data-freq="<?php echo (int) $count; ?>"
              >
                <span class="ledf-ball <?php echo htmlspecialchars($ballClass, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo (int) $count; ?> draws">
                  <?php echo (int) $d; ?>
                </span>
                <div class="ledf-count"><?php echo (int) $count; ?> drws</div>
                <div class="ledf-bar" aria-hidden="true"><span style="width:<?php echo (int) $barWidth; ?>%"></span></div>
              </div>
            <?php endfor; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="skai-note">
        The heatmap shows relative frequency <em>within each position independently</em>. A digit colored green in one position may be less frequent globally — always interpret color relative to its own position column.
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════════════════
       DRAW CONTEXT SECTION
       ═══════════════════════════════════════════════════════════════════════ -->
  <section id="draw-context" class="skai-section" aria-labelledby="draw-context-title">
    <div class="skai-section-head">
      <div>
        <h2 id="draw-context-title" class="skai-section-title">Draw context and full distribution</h2>
        <p class="skai-section-sub">
          This section makes the most recent draw easier to interpret. For each position, it shows the digit drawn, how often it appeared in the selected window, and how many draws ago it last appeared in that same position. The chart shows the complete aggregated digit distribution across all positions.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-two-col">

        <div class="skai-card">
          <div class="skai-card-head skai-card-head--radiant">
            Current draw — per-position context
            <span class="skai-card-sub">Digit drawn, position frequency, and recency for the latest result</span>
          </div>
          <div class="skai-card-body">
            <?php if (!empty($latestDrawContext)) : ?>
              <div class="skai-history-list">
                <?php foreach ($latestDrawContext as $ctx) : ?>
                  <div class="skai-history-item">
                    <div class="skai-history-name">
                      <?php echo htmlspecialchars($ctx['position'], ENT_QUOTES, 'UTF-8'); ?>
                      &mdash; Digit <strong><?php echo htmlspecialchars($ctx['digit'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="skai-history-date">
                      Appeared <?php echo (int) $ctx['freq']; ?> time<?php echo $ctx['freq'] !== 1 ? 's' : ''; ?> in this position across the last <?php echo (int) $drawRange; ?> draws
                    </div>
                    <div class="skai-history-badge">
                      <?php echo htmlspecialchars($ctx['drawsSince'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else : ?>
              <p style="color:var(--text-soft);font-size:14px;">No latest draw data available.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="skai-card">
          <div class="skai-card-head skai-card-head--horizon">
            Full aggregated digit distribution
            <span class="skai-card-sub">All digits 0–9 combined across all <?php echo (int) $positionCount; ?> positions</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-chart-wrap">
              <div class="skai-chart-frame">
                <canvas id="allDigitChart" aria-label="Full aggregated digit distribution chart" role="img"></canvas>
              </div>
            </div>
          </div>
        </div>

      </div>

      <div class="skai-note">
        The aggregated distribution combines all five positions into a single view. Individual position behavior can differ significantly from the aggregate — use the heatmap section above for per-position detail.
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════════════════
       TABLES SECTION
       ═══════════════════════════════════════════════════════════════════════ -->
  <section id="tables" class="skai-section" aria-labelledby="tables-title">
    <div class="skai-section-head">
      <div>
        <h2 id="tables-title" class="skai-section-title">Tables and tracked digits</h2>
        <p class="skai-section-sub">
          Exact counts and recency for every digit in each position. Use the draw window control to adjust the analysis depth. The tracking panel lets you keep a set of digits visible while you compare across sections.
        </p>
      </div>
    </div>

    <div class="skai-controls">
      <form method="post" action="" aria-label="Analysis window controls">
        <div class="skai-controls-row">
          <div class="skai-controls-left">
            <label for="drawRange">Draw window (max <?php echo (int) $totalDrawings; ?>)</label>
            <select class="skai-select" name="drawRange" id="drawRange" aria-describedby="drawRangeHelp">
              <?php
              for ($i = 10; $i <= $totalDrawings; $i += 10) {
                  $sel = ($i === $drawRange) ? ' selected="selected"' : '';
                  echo '<option value="' . (int) $i . '"' . $sel . '>' . (int) $i . ' drawings</option>';
              }
              ?>
            </select>
            <span id="drawRangeHelp" style="font-size:12px;color:var(--text-soft);font-weight:700;">
              Larger windows reduce noise; smaller windows emphasize recency.
            </span>
          </div>
          <div class="skai-controls-right">
            <button class="skai-button" type="submit">Update analysis</button>
            <?php echo HTMLHelper::_('form.token'); ?>
          </div>
        </div>
      </form>
    </div>

    <div class="skai-section-body">

      <div class="skai-two-col">

        <div>
          <?php
          leDfRenderPositionTable($db, $dbCol, 'first',  $drawRange, $gId, 'First Position — Digit Frequency',  'firstPositionTable');
          leDfRenderPositionTable($db, $dbCol, 'third',  $drawRange, $gId, 'Third Position — Digit Frequency',  'thirdPositionTable');
          leDfRenderPositionTable($db, $dbCol, 'fifth',  $drawRange, $gId, 'Fifth Position — Digit Frequency',  'fifthPositionTable');
          ?>
        </div>

        <div>
          <?php
          leDfRenderPositionTable($db, $dbCol, 'second', $drawRange, $gId, 'Second Position — Digit Frequency', 'secondPositionTable');
          leDfRenderPositionTable($db, $dbCol, 'fourth', $drawRange, $gId, 'Fourth Position — Digit Frequency', 'fourthPositionTable');
          ?>

          <div class="skai-tracked">
            <div class="skai-tracked-head">
              <h3 class="skai-tracked-title">Tracked digits</h3>
              <div class="skai-tracked-actions">
                <button class="skai-link-btn" type="button" id="clearTracked">Clear all</button>
              </div>
            </div>
            <div class="skai-chip-wrap" id="trackedWrap">
              <div class="skai-empty">Use the checkboxes in the table to create a tracked digit set for comparison.</div>
            </div>
          </div>

          <div class="skai-note">
            Tracking is local to this page view. It provides a lightweight comparison aid while you move between the overview, heatmap, and advanced SKAI tools.
          </div>
        </div>

      </div>

      <div class="skai-card" style="margin-top:16px;">
        <div class="skai-card-head skai-card-head--radiant">
          Top 10 most drawn 5-digit results
          <span class="skai-card-sub">Most frequently repeated complete results in last <?php echo (int) $drawRange; ?> draws</span>
        </div>
        <div class="skai-table-wrap">
          <table id="overallFrequencyTable" class="skai-table" aria-label="Overall top 5-digit combinations">
            <thead>
              <tr>
                <th>Result</th>
                <th>Drawn Times</th>
                <th>Drawings Ago</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $overallCount = 0;
              foreach ($overall as $number => $freq) {
                  $ago = leDfGetDrawingsAgo($db, $dbCol, (string) $number, $gId, $drawRange);

                  echo '<tr><td style="white-space:nowrap;">';
                  foreach (str_split((string) $number) as $digit) {
                      echo '<span class="skai-pill skai-pill--main" style="margin:2px;">' . htmlspecialchars((string) $digit, ENT_QUOTES, 'UTF-8') . '</span>';
                  }
                  echo '</td>';
                  echo '<td>' . (int) $freq . ' ×</td>';
                  echo '<td class="skai-drawings-ago">' . htmlspecialchars($ago, ENT_QUOTES, 'UTF-8') . '</td>';
                  echo '</tr>';

                  $overallCount++;
                  if ($overallCount >= 10) {
                      break;
                  }
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════════════════
       ADVANCED TOOLS SECTION
       ═══════════════════════════════════════════════════════════════════════ -->
  <section id="tools" class="skai-section" aria-labelledby="tools-title">
    <div class="skai-section-head">
      <div>
        <h2 id="tools-title" class="skai-section-title">Next steps and advanced tools</h2>
        <p class="skai-section-sub">
          The digit frequency view establishes positional context. These tools take that context into deeper modeling and structured exploration.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-tool-grid">
        <article class="skai-tool">
          <div class="skai-tool-head skai-card-head--horizon">SKAI Analysis</div>
          <div class="skai-tool-body">
            <p class="skai-tool-copy">
              Best next step for a broader multi-signal view. Use this after reviewing digit frequency and position patterns to move into the main SKAI intelligence workflow.
            </p>
            <a class="skai-tool-cta" href="<?php echo htmlspecialchars($aiHref, ENT_QUOTES, 'UTF-8'); ?>">Open SKAI Analysis</a>
          </div>
        </article>

        <article class="skai-tool">
          <div class="skai-tool-head skai-card-head--radiant">AI Predictions</div>
          <div class="skai-tool-body">
            <p class="skai-tool-copy">
              Use when you want a model-driven complement to the positional frequency view shown on this page.
            </p>
            <a class="skai-tool-cta" href="/picking-winning-numbers/artificial-intelligence/ai-powered-predictions?game_id=<?php echo rawurlencode($gId); ?>">Open AI Predictions</a>
          </div>
        </article>

        <article class="skai-tool">
          <div class="skai-tool-head skai-card-head--success">Skip &amp; Hit Analysis</div>
          <div class="skai-tool-body">
            <p class="skai-tool-copy">
              Useful for users who want to compare digit appearance spacing and interruption behavior after reviewing current frequency.
            </p>
            <a class="skai-tool-cta" href="/picking-winning-numbers/artificial-intelligence/skip-and-hit-analysis?game_id=<?php echo rawurlencode($gId); ?>">Open Skip &amp; Hit</a>
          </div>
        </article>
      </div>

      <div class="skai-utility-grid">
        <a class="skai-utility-link" href="/picking-winning-numbers/artificial-intelligence/markov-chain-monte-carlo-mcmc-analysis?game_id=<?php echo rawurlencode($gId); ?>">MCMC Markov Analysis</a>
        <a class="skai-utility-link" href="/all-lottery-heatmaps?gId=<?php echo rawurlencode($gId); ?>&amp;stateName=<?php echo rawurlencode($stateName); ?>&amp;gName=<?php echo rawurlencode($gName); ?>&amp;sTn=<?php echo rawurlencode(strtolower($stateAbrev)); ?>">Heatmap Analysis</a>
        <a class="skai-utility-link" href="/lottery-archives?gId=<?php echo rawurlencode($gId); ?>&amp;stateName=<?php echo rawurlencode($stateName); ?>&amp;gName=<?php echo rawurlencode($gName); ?>&amp;sTn=<?php echo rawurlencode(strtolower($stateAbrev)); ?>">Lottery Archives</a>
        <a class="skai-utility-link" href="/lowest-drawn-number-analysis?gId=<?php echo rawurlencode($gId); ?>&amp;stateName=<?php echo rawurlencode($stateName); ?>&amp;gName=<?php echo rawurlencode($gName); ?>&amp;sTn=<?php echo rawurlencode(strtolower($stateAbrev)); ?>">Lowest Number Analysis</a>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════════════════
       METHOD NOTE
       ═══════════════════════════════════════════════════════════════════════ -->
  <section class="skai-section" aria-labelledby="method-title">
    <div class="skai-section-head">
      <div>
        <h2 id="method-title" class="skai-section-title">Method note</h2>
        <p class="skai-section-sub">
          This page is designed to help users understand how digits distribute across draw positions — not to imply certainty about future outcomes.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-method-note">
        <strong>What digit frequency means:</strong> Each lottery draw assigns one digit (0–9) to each of <?php echo (int) $positionCount; ?> positions. Frequency counts how often each digit appeared in a specific position across recent draws. A digit appearing more often in a position simply means the historical record favors it in that position within the selected window — it does not carry forward to future draws.<br><br>
        <strong>What it does not mean:</strong> Frequency concentration is a descriptive statistic. Lottery outcomes are independent events. No positional pattern, however consistent, guarantees a future result. Use this analysis as context for deeper SKAI review, not as a prediction engine.<br><br>
        <strong>Best use:</strong> Review the per-position heatmap, identify your own positions of interest, then carry that context into the SKAI analysis workflow for a more complete multi-signal view.
      </div>
    </div>
  </section>

</div>

<script type="text/javascript">
(function () {
  'use strict';

  var chartData = {
    topActiveLabels: <?php echo json_encode(array_values($topActiveDigitKeys)); ?>,
    topActiveValues: <?php echo json_encode(array_values($topActiveDigitValues)); ?>,
    quietLabels: <?php echo json_encode(array_values($quietDigitKeys)); ?>,
    quietValues: <?php echo json_encode(array_values($quietDigitValues)); ?>,
    allDigitLabels: <?php echo json_encode(array_values($allDigitChartLabels)); ?>,
    allDigitValues: <?php echo json_encode(array_values($allDigitChartValues)); ?>
  };

  /* ── Chart.js loader ──────────────────────────────────────────────────── */
  function loadChartJsIfNeeded(done) {
    if (window.Chart) {
      done();
      return;
    }

    var cdnUrl   = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    var integrity = 'sha384-OLBgp1GsljhM2TJ+sbHjaiH9txEUvgdDTAzHv2P24donTt6/529l+9Ua0vFImLlb';

    function tryLoad(withIntegrity) {
      var script    = document.createElement('script');
      script.src    = cdnUrl;
      script.async  = true;

      if (withIntegrity) {
        script.integrity    = integrity;
        script.crossOrigin  = 'anonymous';
      }

      script.onload = function () {
        done();
      };

      script.onerror = function () {
        if (withIntegrity) {
          tryLoad(false);
        } else {
          done();
        }
      };

      document.head.appendChild(script);
    }

    tryLoad(true);
  }

  /* ── Common chart options ─────────────────────────────────────────────── */
  function commonBarOptions(horizontal) {
    return {
      responsive: true,
      maintainAspectRatio: false,
      indexAxis: horizontal ? 'y' : 'x',
      animation: false,
      plugins: {
        legend: { display: false },
        tooltip: { enabled: true }
      },
      scales: horizontal ? {
        x: {
          beginAtZero: true,
          ticks: { precision: 0, font: { weight: '700' } },
          grid: { color: 'rgba(10,26,51,.08)' }
        },
        y: {
          ticks: { autoSkip: false, font: { weight: '700' } },
          grid: { display: false }
        }
      } : {
        x: {
          ticks: { font: { weight: '700' } },
          grid: { display: false }
        },
        y: {
          beginAtZero: true,
          ticks: { precision: 0, font: { weight: '700' } },
          grid: { color: 'rgba(10,26,51,.08)' }
        }
      }
    };
  }

  /* ── Render charts ────────────────────────────────────────────────────── */
  function renderCharts() {
    if (!window.Chart) {
      return;
    }

    var topActiveCanvas = document.getElementById('topActiveChart');
    var quietCanvas     = document.getElementById('quietChart');
    var allDigitCanvas  = document.getElementById('allDigitChart');

    if (topActiveCanvas && !topActiveCanvas._chartInited) {
      topActiveCanvas._chartInited = true;
      new Chart(topActiveCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.topActiveLabels,
          datasets: [{
            data: chartData.topActiveValues,
            borderWidth: 0,
            borderRadius: 8,
            backgroundColor: '#1C66FF'
          }]
        },
        options: commonBarOptions(false)
      });
    }

    if (quietCanvas && !quietCanvas._chartInited) {
      quietCanvas._chartInited = true;
      new Chart(quietCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.quietLabels,
          datasets: [{
            data: chartData.quietValues,
            borderWidth: 0,
            borderRadius: 8,
            backgroundColor: '#F5A623'
          }]
        },
        options: commonBarOptions(false)
      });
    }

    if (allDigitCanvas && !allDigitCanvas._chartInited) {
      allDigitCanvas._chartInited = true;
      new Chart(allDigitCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.allDigitLabels,
          datasets: [{
            data: chartData.allDigitValues,
            borderWidth: 0,
            borderRadius: 8,
            backgroundColor: [
              '#1C66FF','#3A7EFF','#5896FF','#76AEFF','#94C6FF',
              '#B2DEFF','#7F8DAA','#5F6F8C','#0A1A33','#20C997'
            ]
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          plugins: {
            legend: { display: false },
            tooltip: { enabled: true }
          },
          scales: {
            x: {
              ticks: { font: { weight: '700' } },
              grid: { display: false }
            },
            y: {
              beginAtZero: true,
              ticks: { precision: 0, font: { weight: '700' } },
              grid: { color: 'rgba(10,26,51,.08)' }
            }
          }
        }
      });
    }
  }

  /* ── Heatmap sort control ─────────────────────────────────────────────── */
  function bindSortControl() {
    var sel = document.getElementById('heatmapSort');

    if (!sel) {
      return;
    }

    sel.addEventListener('change', function () {
      var mode  = this.value;
      var grids = document.querySelectorAll('.ledf-digit-grid[data-sortgrid="1"]');

      for (var i = 0; i < grids.length; i++) {
        var grid  = grids[i];
        var cells = Array.prototype.slice.call(grid.querySelectorAll('.ledf-digit-cell'));

        cells.sort(function (a, b) {
          var af = parseInt(a.getAttribute('data-freq'), 10) || 0;
          var bf = parseInt(b.getAttribute('data-freq'), 10) || 0;

          if (mode === 'high') { return bf - af; }
          if (mode === 'low')  { return af - bf; }

          var ad = parseInt(a.getAttribute('data-digit'), 10) || 0;
          var bd = parseInt(b.getAttribute('data-digit'), 10) || 0;
          return ad - bd;
        });

        for (var c = 0; c < cells.length; c++) {
          grid.appendChild(cells[c]);
        }
      }
    });
  }

  /* ── DataTables init ──────────────────────────────────────────────────── */
  function initDataTable(id, dir) {
    if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) { return; }
    if (window.jQuery(id).length === 0) { return; }
    if (window.jQuery(id).hasClass('dataTable')) { return; }

    window.jQuery(id).DataTable({
      order: [[1, dir]],
      paging: false,
      bLengthChange: false,
      bFilter: false,
      bInfo: false
    });
  }

  function bindDataTables() {
    var tries = 0;
    var timer = setInterval(function () {
      tries++;

      initDataTable('#firstPositionTable',  'asc');
      initDataTable('#secondPositionTable', 'asc');
      initDataTable('#thirdPositionTable',  'asc');
      initDataTable('#fourthPositionTable', 'asc');
      initDataTable('#fifthPositionTable',  'asc');
      initDataTable('#overallFrequencyTable', 'desc');

      if ((window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) || tries >= 30) {
        clearInterval(timer);
      }
    }, 100);
  }

  /* ── Tracking ─────────────────────────────────────────────────────────── */
  function bindTracking() {
    var wrap       = document.getElementById('trackedWrap');
    var clearBtn   = document.getElementById('clearTracked');
    var emptyText  = 'Use the checkboxes in the table to create a tracked digit set for comparison.';

    if (!wrap) {
      return;
    }

    function renderTracked() {
      var inputs = document.querySelectorAll('.js-track-digit');
      var items  = [];

      for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].checked) {
          items.push(inputs[i].value);
        }
      }

      wrap.innerHTML = '';

      if (!items.length) {
        var empty     = document.createElement('div');
        empty.className = 'skai-empty';
        empty.textContent = emptyText;
        wrap.appendChild(empty);
        return;
      }

      for (var j = 0; j < items.length; j++) {
        var chip = document.createElement('span');
        chip.className   = 'skai-chip';
        chip.textContent = items[j];
        wrap.appendChild(chip);
      }
    }

    var inputs = document.querySelectorAll('.js-track-digit');
    for (var i = 0; i < inputs.length; i++) {
      inputs[i].addEventListener('change', renderTracked);
    }

    renderTracked();

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        var all = document.querySelectorAll('.js-track-digit');
        for (var i = 0; i < all.length; i++) {
          all[i].checked = false;
        }
        renderTracked();
      });
    }
  }

  /* ── Tab nav active state ─────────────────────────────────────────────── */
  function initAnchors() {
    var tabs = document.querySelectorAll('.skai-tab');

    if (!tabs.length) {
      return;
    }

    for (var i = 0; i < tabs.length; i++) {
      tabs[i].addEventListener('click', function () {
        for (var j = 0; j < tabs.length; j++) {
          tabs[j].classList.remove('skai-tab--active');
        }
        this.classList.add('skai-tab--active');
      });
    }
  }

  /* ── Bootstrap ────────────────────────────────────────────────────────── */
  function init() {
    bindSortControl();
    bindDataTables();
    bindTracking();
    initAnchors();
    loadChartJsIfNeeded(renderCharts);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
</script>
