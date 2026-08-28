<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Detects this host's own CPU/RAM so local_quizanalytics/parallelworkers
 * can default to something sized for the actual server running it, instead
 * of a fixed number picked against whatever machine happened to develop
 * this plugin. Confirmed directly why this matters: this session's own
 * 10-core/7.9GB local Docker host and a smaller shared production box need
 * genuinely different defaults, and there was previously no way to tell
 * the difference without reading code.
 *
 * Reads cgroup v2 limits first (memory.max, cpu.max) — the accurate figure
 * on a genuinely resource-capped container/host — falling back to
 * /proc/meminfo and /proc/cpuinfo (the *physical* host's own figures) when
 * cgroup either isn't present or reports "max" (no limit actually
 * imposed), which is the common case for a host that isn't deliberately
 * capped. Every detection step fails safe: any missing/unreadable file,
 * unparseable content, or genuinely undetectable value (e.g. a restricted
 * container, a non-Linux host) returns null rather than guessing or
 * throwing, and every caller in this class treats null as "detection
 * didn't work here" and falls back to the same conservative static
 * defaults this plugin already shipped with, never blank/broken config.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Host CPU/RAM detection for sizing local_quizanalytics's own resource-dependent defaults.
 */
class resource_detector {
    /** Never recommend more workers than this, regardless of how many cores/how much RAM a host reports. */
    const MAX_RECOMMENDED_WORKERS = 16;

    /** Fallback worker count used when detection fails — matches this plugin's original static default. */
    const FALLBACK_WORKERS = 4;

    /**
     * Planning figure (MB) for a worker's *typical* memory footprint, used
     * only to size the recommended worker count — deliberately smaller
     * than local_quizanalytics/parallelworkermemory's own default (2048MB).
     * That setting is a defensive PHP memory_limit ceiling sized for the
     * occasional outlier (one very large quiz landing on one worker); this
     * is what a worker actually tends to use in practice. Measured
     * directly this session: per-quiz fetch memory after the
     * ATTEMPT_BATCH_SIZE chunking fix peaked around 366MB even for the
     * largest real quiz tested, and real multi-worker runs on a 10-core/
     * 7.9GB host averaged well under 1GB/worker at every tested
     * concurrency level (6 workers: ~841MB/worker peak; 12 workers:
     * ~613MB/worker peak). Sizing the worker-count recommendation off the
     * full 2048MB ceiling instead of a realistic typical figure would
     * recommend far fewer workers than this session already proved safe
     * and beneficial on that same host.
     */
    const TYPICAL_WORKER_MEMORY_MB = 1024;

    /**
     * @return int|null CPU core count, or null if it couldn't be determined.
     */
    public static function detect_cpu_cores(): ?int {
        $cgroup = self::detect_cpu_cores_cgroup();
        if ($cgroup !== null) {
            return $cgroup;
        }
        return self::detect_cpu_cores_proc();
    }

    /**
     * cgroup v2's own CPU quota (cpu.max, "$quota $period" in microseconds,
     * or literally "max" for no limit) — how many whole cores this
     * specific container/cgroup is actually allowed to use, which can be
     * lower than the physical host's own core count.
     *
     * @return int|null
     */
    private static function detect_cpu_cores_cgroup(): ?int {
        $path = '/sys/fs/cgroup/cpu.max';
        if (!is_readable($path)) {
            return null;
        }
        $content = trim((string) @file_get_contents($path));
        if ($content === '' || str_starts_with($content, 'max')) {
            return null; // No quota actually imposed — fall back to the physical host figure.
        }
        $parts = preg_split('/\s+/', $content);
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1]) || (float) $parts[1] <= 0) {
            return null;
        }
        $cores = (int) floor(((float) $parts[0]) / ((float) $parts[1]));
        return $cores > 0 ? $cores : null;
    }

    /**
     * The physical (or VM-level) host's own CPU core count, via
     * /proc/cpuinfo — the same figure `nproc` reports, without relying on
     * shell_exec() (routinely disabled on shared hosting).
     *
     * @return int|null
     */
    private static function detect_cpu_cores_proc(): ?int {
        $path = '/proc/cpuinfo';
        if (!is_readable($path)) {
            return null;
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }
        $count = preg_match_all('/^processor\s*:/m', $content);
        return $count > 0 ? $count : null;
    }

    /**
     * @return int|null available memory in MB, or null if it couldn't be determined.
     */
    public static function detect_memory_mb(): ?int {
        $cgroup = self::detect_memory_mb_cgroup();
        if ($cgroup !== null) {
            return $cgroup;
        }
        return self::detect_memory_mb_proc();
    }

    /**
     * cgroup v2's own memory limit (memory.max, in bytes, or literally
     * "max" for no limit) — the real ceiling for this specific
     * container/cgroup, which can be lower than the physical host's own
     * total RAM.
     *
     * @return int|null
     */
    private static function detect_memory_mb_cgroup(): ?int {
        $path = '/sys/fs/cgroup/memory.max';
        if (!is_readable($path)) {
            return null;
        }
        $content = trim((string) @file_get_contents($path));
        if ($content === '' || $content === 'max' || !ctype_digit($content)) {
            return null; // No limit actually imposed — fall back to the physical host figure.
        }
        $mb = (int) ((float) $content / 1048576);
        return $mb > 0 ? $mb : null;
    }

    /**
     * The physical (or VM-level) host's own total memory, via
     * /proc/meminfo's MemTotal — deliberately the *total capacity* figure,
     * not MemAvailable's "safe to use right this moment" one: this feeds a
     * one-time, persisted config default (see recommend_parallel_workers()
     * and this class's own "Re-detect" button on the admin settings page),
     * which should reflect what this host generally has to offer, not
     * whatever happened to be free from unrelated load at the exact moment
     * detection ran — MemAvailable can swing wildly run to run on a busy
     * box in a way that would make "Re-detect" look flaky rather than
     * genuinely reflecting a hardware change. cgroup's own memory.max
     * (checked first, above) is already this same "total ceiling" shape,
     * for consistency. recommend_parallel_workers()'s own $memoryfraction
     * safety margin is what actually leaves room for everything else
     * running on the host, same as this plugin's own parallelworkermemory
     * setting already documents.
     *
     * @return int|null
     */
    private static function detect_memory_mb_proc(): ?int {
        $path = '/proc/meminfo';
        if (!is_readable($path)) {
            return null;
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }
        if (!preg_match('/^MemTotal:\s+(\d+)\s*kB/m', $content, $matches)) {
            return null;
        }
        $mb = (int) ((float) $matches[1] / 1024);
        return $mb > 0 ? $mb : null;
    }

    /**
     * Recommends a local_quizanalytics/parallelworkers value for this
     * host: bounded by CPU core count (the fetch stage's own PHP/DB work
     * is genuinely CPU-bound once a course's real per-attempt CAS cost is
     * warm — see this session's own worker-count benchmarking notes for
     * why oversubscribing past core count stopped helping and started
     * hurting on a 10-core test host) and by how many
     * $workermemorymb-sized workers this host's own available memory can
     * hold at once, at a conservative fraction of it — leaving real room
     * for MariaDB, the Maxima backend, and everything else already running
     * on the same host, the same sizing philosophy this plugin's own
     * parallelworkermemory setting description already documents.
     *
     * Falls back to this plugin's original static default (4) whenever
     * either figure can't be detected, rather than guessing.
     *
     * @param int $workermemorymb the local_quizanalytics/parallelworkermemory value — respected as a hard
     *        cap if an admin has set it *below* TYPICAL_WORKER_MEMORY_MB (e.g. deliberately tightened on a
     *        memory-constrained host), otherwise TYPICAL_WORKER_MEMORY_MB is used for planning instead of
     *        this setting's own (defensive, worst-case) value
     * @param float $memoryfraction never plan to use more than this share of detected available memory
     * @return array{workers: int, cores: int|null, memorymb: int|null, source: string} source is
     *         'detected' when both cores and memory were determined, 'fallback' otherwise.
     */
    public static function recommend_parallel_workers(int $workermemorymb, float $memoryfraction = 0.5): array {
        $cores = self::detect_cpu_cores();
        $memorymb = self::detect_memory_mb();

        if ($cores === null || $memorymb === null || $workermemorymb <= 0) {
            return [
                'workers' => self::FALLBACK_WORKERS,
                'cores' => $cores,
                'memorymb' => $memorymb,
                'source' => 'fallback',
            ];
        }

        $planningmb = min($workermemorymb, self::TYPICAL_WORKER_MEMORY_MB);
        $bymemory = (int) floor(($memorymb * $memoryfraction) / $planningmb);
        $workers = max(1, min($cores, $bymemory, self::MAX_RECOMMENDED_WORKERS));

        return [
            'workers' => $workers,
            'cores' => $cores,
            'memorymb' => $memorymb,
            'source' => 'detected',
        ];
    }
}
