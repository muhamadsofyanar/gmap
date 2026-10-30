<?php
/**
 * Scraper Engine for Gmap Prospek
 * Runs the Python/Selenium scraper and preserves missing data.
 */

namespace App\Services;

class ScraperService
{
    protected const PROCESS_TIMEOUT_SECONDS = 120;

    public function fetchOpeningHours(string $url): ?string
    {
        $identity = MapsIdentity::key($url);
        if (!$identity || parse_url($url, PHP_URL_SCHEME) !== 'https') return null;
        $items = $this->scrapeViaHeadlessChrome('--hours', $url, 5);
        foreach ($items as $item) {
            if (MapsIdentity::key($item['gmap_url'] ?? '') === $identity && is_string($item['opening_hours'] ?? null) && trim($item['opening_hours']) !== '') {
                return trim($item['opening_hours']);
            }
        }
        return null;
    }

    /** Stop extraction once the requested number of qualified prospects is found. */
    public function scrapePotential(string $keyword, string $location, int $limit = 20): array
    {
        $limit = max(5, min(100, $limit));
        $result = $this->scrape($keyword, $location, $limit, true);
        if (!$result['success']) return $result;
        $result['items'] = LeadQualification::filter($result['items'], $limit);
        $result['total'] = count($result['items']);
        $result['message'] = $result['total'] > 0
            ? "Ditemukan {$result['total']} lead potensial dari target {$limit}."
            : 'Belum ditemukan lead dengan nomor kontak. Coba kata kunci atau lokasi lain.';
        return $result;
    }

    /**
     * Scrape real Google Maps prospects
     *
     * @param string $keyword Search keyword (e.g. 'Bengkel Motor', 'Coffee Shop')
     * @param string $location Location/City (e.g. 'Surabaya', 'Jakarta Selatan')
     * @param int $limit Target number of results (5 - 100)
     * @return array
     */
    public function scrape(string $keyword, string $location, int $limit = 20, bool $potentialOnly = false): array
    {
        $startTime = microtime(true);
        $limit = max(5, min(100, $limit));

        $cleanKeyword = trim($keyword);
        $cleanLocation = trim($location);

        $failureMessage = 'Data Google Maps belum berhasil diperoleh. Coba lagi atau ubah pencarian. Jika berulang, periksa Python, Selenium, dan Chrome pada server.';
        try {
            $items = $this->scrapeViaHeadlessChrome($cleanKeyword, $cleanLocation, $limit, $potentialOnly);
        } catch (\Throwable $e) {
            error_log('Google Maps scraper: ' . $e->getMessage());
            if ($e instanceof ScraperTimeoutException) {
                $failureMessage = 'Pencarian melewati batas waktu. Google Maps belum selesai merespons. Coba target lebih kecil atau lokasi yang lebih spesifik. Proyek belum dibuat.';
            }
            $items = [];
        }
        if (empty($items)) {
            return [
                'success' => false, 'items' => [], 'total' => 0,
                'execution_time' => round(microtime(true) - $startTime, 3),
                'message' => $failureMessage
            ];
        }
        foreach ($items as &$item) {
            // Never fabricate missing contact information.
            $item['phone'] = trim((string)($item['phone'] ?? ''));
            $item['whatsapp'] = format_whatsapp($item['phone']);
        }
        unset($item);

        // Limit results to requested count
        $items = $potentialOnly ? LeadQualification::filter($items, $limit) : array_slice($items, 0, $limit);

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 3);

        return [
            'success' => true,
            'items' => $items,
            'execution_time' => $executionTime,
            'total' => count($items),
            'source' => 'google_maps_headless_scraper'
        ];
    }

    /**
     * Run Python Headless Chrome Scraper
     *
     * @param string $keyword
     * @param string $location
     * @param int $limit
     * @return array
     */
    protected function scraperCommand(string $keyword, string $location, int $limit, bool $potentialOnly): array
    {
        // Linux servers ship python3 (usually inside a virtualenv set via PYTHON_BIN).
        $python = defined('PYTHON_BIN') ? PYTHON_BIN : (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
        $command = [$python, __DIR__ . '/scraper_gmap.py', $keyword, $location, (string)$limit];
        if ($potentialOnly) $command[] = '--potential';
        return $command;
    }

    protected function scrapeViaHeadlessChrome(string $keyword, string $location, int $limit, bool $potentialOnly = false): array
    {
        $scriptPath = __DIR__ . '/scraper_gmap.py';
        if (!file_exists($scriptPath)) {
            return [];
        }

        $stdout = tmpfile();
        $stderr = tmpfile();
        if (!$stdout || !$stderr) {
            if ($stdout) fclose($stdout);
            if ($stderr) fclose($stderr);
            throw new \RuntimeException('Cannot create scraper output streams.');
        }
        $process = null;
        $originalTimeLimit = (int)ini_get('max_execution_time');
        $requestTimeLimit = static::PROCESS_TIMEOUT_SECONDS + 30;
        $extendedTimeLimit = false;
        try {
            // On Windows PHP counts the time spent waiting for Python. Let the
            // bounded subprocess deadline expire before PHP's uncatchable fatal timeout.
            if ($originalTimeLimit > 0 && $originalTimeLimit < $requestTimeLimit) {
                if (!set_time_limit($requestTimeLimit)) {
                    throw new \RuntimeException('Cannot extend the PHP scraper request budget.');
                }
                $extendedTimeLimit = true;
            }
            $deadline = microtime(true) + static::PROCESS_TIMEOUT_SECONDS;
            // Argument arrays bypass shell interpretation, including on Windows.
            $process = proc_open(
                $this->scraperCommand($keyword, $location, $limit, $potentialOnly),
                [0 => ['pipe', 'r'], 1 => $stdout, 2 => $stderr],
                $pipes, null, null, ['bypass_shell' => true]
            );
            if (!is_resource($process)) throw new \RuntimeException('Cannot start Python scraper.');
            fclose($pipes[0]);
            do {
                $status = proc_get_status($process);
                if (!$status['running']) break;
                if (microtime(true) >= $deadline) {
                    proc_terminate($process);
                    throw new ScraperTimeoutException('Scraper timed out.');
                }
                usleep(100000);
            } while (true);
            rewind($stdout);
            $decoded = json_decode(stream_get_contents($stdout), true);
            if ($status['exitcode'] !== 0 || !is_array($decoded) || empty($decoded['success']) || !is_array($decoded['items'] ?? null)) {
                rewind($stderr);
                throw new \RuntimeException('Scraper failed: ' . substr(stream_get_contents($stderr), 0, 2000));
            }
            return $decoded['items'];
        } finally {
            if (is_resource($process)) proc_close($process);
            fclose($stdout);
            fclose($stderr);
            if ($extendedTimeLimit) set_time_limit($originalTimeLimit);
        }
    }
}
