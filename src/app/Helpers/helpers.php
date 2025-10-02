<?php

use App\Helpers\StopwatchHelper;

if (!function_exists('stopwatch')) {
    /**
     * Create or get a stopwatch instance
     * 
     * @param string $name The name of the stopwatch instance
     * @return StopwatchHelper
     */
    function stopwatch($name = 'default')
    {
        return StopwatchHelper::create($name);
    }
}

if (!function_exists('timer_start')) {
    /**
     * Start a named timer
     * 
     * @param string $name The name of the timer
     * @return StopwatchHelper
     */
    function timer_start($name = 'default')
    {
        return stopwatch($name)->start();
    }
}

if (!function_exists('timer_stop')) {
    /**
     * Stop a named timer and return elapsed time
     * 
     * @param string $name The name of the timer
     * @return array
     */
    function timer_stop($name = 'default')
    {
        $timer = stopwatch($name)->stop();
        return [
            'elapsed' => $timer->getElapsed(),
            'formatted' => $timer->getFormattedElapsed()
        ];
    }
}

if (!function_exists('timer_elapsed')) {
    /**
     * Get elapsed time for a running timer
     * 
     * @param string $name The name of the timer
     * @param bool $formatted Whether to return formatted time
     * @return string|float
     */
    function timer_elapsed($name = 'default', $formatted = false)
    {
        $timer = stopwatch($name);
        return $formatted ? $timer->getFormattedElapsed() : $timer->getElapsed();
    }
}

if (!function_exists('timer_lap')) {
    /**
     * Record a lap time
     * 
     * @param string $name The name of the timer
     * @param string|null $label Optional label for the lap
     * @return array
     */
    function timer_lap($name = 'default', $label = null)
    {
        return stopwatch($name)->lap($label);
    }
}

if (!function_exists('timer_log')) {
    /**
     * Log the current timer status
     * 
     * @param string $name The name of the timer
     * @param string|null $message Optional message to include
     * @param string $level Log level (info, debug, warning, error)
     * @return StopwatchHelper
     */
    function timer_log($name = 'default', $message = null, $level = 'info')
    {
        return stopwatch($name)->log($message, $level);
    }
}

if (!function_exists('timer_summary')) {
    /**
     * Get timer summary
     * 
     * @param string $name The name of the timer
     * @return array
     */
    function timer_summary($name = 'default')
    {
        return stopwatch($name)->getSummary();
    }
}

if (!function_exists('time_function')) {
    /**
     * Time the execution of a function
     * 
     * @param callable $callback The function to time
     * @param string $name Optional name for the timer
     * @return array
     */
    function time_function($callback, $name = 'quick_timer')
    {
        return StopwatchHelper::time($callback, $name);
    }
}
