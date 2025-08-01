<?php

namespace App\Helpers;

use Carbon\Carbon;

class StopwatchHelper
{
    private static $instances = [];
    private $startTime;
    private $endTime;
    private $laps = [];
    private $name;

    public function __construct($name = 'default')
    {
        $this->name = $name;
        $this->startTime = null;
        $this->endTime = null;
        $this->laps = [];
    }

    /**
     * Create or get a named stopwatch instance
     */
    public static function create($name = 'default')
    {
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new self($name);
        }
        return self::$instances[$name];
    }

    /**
     * Start the stopwatch
     */
    public function start()
    {
        $this->startTime = microtime(true);
        $this->endTime = null;
        $this->laps = [];
        return $this;
    }

    /**
     * Stop the stopwatch
     */
    public function stop()
    {
        if ($this->startTime === null) {
            throw new \Exception("Stopwatch '{$this->name}' has not been started yet.");
        }
        
        $this->endTime = microtime(true);
        return $this;
    }

    /**
     * Record a lap time without stopping the stopwatch
     */
    public function lap($label = null)
    {
        if ($this->startTime === null) {
            throw new \Exception("Stopwatch '{$this->name}' has not been started yet.");
        }

        $lapTime = microtime(true);
        $elapsed = $lapTime - $this->startTime;
        
        $lapData = [
            'time' => $lapTime,
            'elapsed' => $elapsed,
            'label' => $label ?? 'Lap ' . (count($this->laps) + 1),
            'formatted_elapsed' => $this->formatTime($elapsed)
        ];
        
        $this->laps[] = $lapData;
        return $lapData;
    }

    /**
     * Get elapsed time in seconds (with microseconds)
     */
    public function getElapsed()
    {
        if ($this->startTime === null) {
            throw new \Exception("Stopwatch '{$this->name}' has not been started yet.");
        }

        $endTime = $this->endTime ?? microtime(true);
        return $endTime - $this->startTime;
    }

    /**
     * Get formatted elapsed time
     */
    public function getFormattedElapsed()
    {
        return $this->formatTime($this->getElapsed());
    }

    /**
     * Get all lap times
     */
    public function getLaps()
    {
        return $this->laps;
    }

    /**
     * Reset the stopwatch
     */
    public function reset()
    {
        $this->startTime = null;
        $this->endTime = null;
        $this->laps = [];
        return $this;
    }

    /**
     * Check if stopwatch is running
     */
    public function isRunning()
    {
        return $this->startTime !== null && $this->endTime === null;
    }

    /**
     * Get stopwatch status summary
     */
    public function getSummary()
    {
        $summary = [
            'name' => $this->name,
            'status' => $this->isRunning() ? 'running' : 'stopped',
            'started_at' => $this->startTime ? Carbon::createFromTimestamp($this->startTime)->format('Y-m-d H:i:s.u') : null,
            'stopped_at' => $this->endTime ? Carbon::createFromTimestamp($this->endTime)->format('Y-m-d H:i:s.u') : null,
            'elapsed' => $this->startTime ? $this->getElapsed() : 0,
            'formatted_elapsed' => $this->startTime ? $this->getFormattedElapsed() : '0.000s',
            'laps_count' => count($this->laps),
            'laps' => $this->laps
        ];

        return $summary;
    }

    /**
     * Log the current elapsed time with a message
     */
    public function log($message = null, $level = 'info')
    {
        $elapsed = $this->getFormattedElapsed();
        $logMessage = $message 
            ? "Stopwatch '{$this->name}': {$message} - Elapsed: {$elapsed}"
            : "Stopwatch '{$this->name}' - Elapsed: {$elapsed}";

        switch ($level) {
            case 'debug':
                logger()->debug($logMessage);
                break;
            case 'warning':
                logger()->warning($logMessage);
                break;
            case 'error':
                logger()->error($logMessage);
                break;
            default:
                logger()->info($logMessage);
        }

        return $this;
    }

    /**
     * Format time duration
     */
    private function formatTime($seconds)
    {
        if ($seconds < 1) {
            return number_format($seconds * 1000, 2) . 'ms';
        } elseif ($seconds < 60) {
            return number_format($seconds, 3) . 's';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return $minutes . 'm ' . number_format($remainingSeconds, 3) . 's';
        } else {
            $hours = floor($seconds / 3600);
            $remainingMinutes = floor(($seconds % 3600) / 60);
            $remainingSeconds = $seconds % 60;
            return $hours . 'h ' . $remainingMinutes . 'm ' . number_format($remainingSeconds, 3) . 's';
        }
    }

    /**
     * Static method to quickly time a function execution
     */
    public static function time($callback, $name = 'quick_timer')
    {
        $stopwatch = self::create($name);
        $stopwatch->start();
        
        $result = $callback();
        
        $stopwatch->stop();
        
        return [
            'result' => $result,
            'elapsed' => $stopwatch->getElapsed(),
            'formatted_elapsed' => $stopwatch->getFormattedElapsed(),
            'summary' => $stopwatch->getSummary()
        ];
    }

    /**
     * Remove a named instance
     */
    public static function destroy($name)
    {
        unset(self::$instances[$name]);
    }

    /**
     * Get all active instances
     */
    public static function getAllInstances()
    {
        return self::$instances;
    }

    /**
     * Clear all instances
     */
    public static function clearAll()
    {
        self::$instances = [];
    }
}
