<?php
/**
 * Pugo Core 3.0 - Simple Logger
 * 
 * PSR-3 inspired simple file logger.
 */

namespace Pugo\Logging;

class Logger
{
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';
    
    private static ?Logger $instance = null;
    private string $logFile;
    private string $minLevel;
    private bool $enabled;
    
    private array $levels = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::NOTICE => 2,
        self::WARNING => 3,
        self::ERROR => 4,
        self::CRITICAL => 5,
        self::ALERT => 6,
        self::EMERGENCY => 7,
    ];
    
    private function __construct(?string $logFile = null, string $minLevel = self::DEBUG)
    {
        $this->logFile = $logFile ?? (defined('HUGO_ROOT') ? HUGO_ROOT : getcwd()) . '/admin/logs/pugo.log';
        $this->minLevel = $minLevel;
        $this->enabled = true;
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public static function getInstance(?string $logFile = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($logFile);
        }
        return self::$instance;
    }
    
    /**
     * Enable/disable logging
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }
    
    /**
     * Set minimum log level
     */
    public function setMinLevel(string $level): self
    {
        $this->minLevel = $level;
        return $this;
    }
    
    /**
     * Log a message
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        // Check level
        if (($this->levels[$level] ?? 0) < ($this->levels[$this->minLevel] ?? 0)) {
            return;
        }
        
        // Format message
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        
        // Interpolate context
        $message = $this->interpolate($message, $context);
        
        // Add context as JSON if not empty
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' ' . json_encode($context);
        }
        
        $line = "[{$timestamp}] [{$levelUpper}] {$message}{$contextStr}\n";
        
        // Append to log file
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Interpolate context values into message
     */
    protected function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val)) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }
    
    // Convenience methods
    
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }
    
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }
    
    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }
    
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }
    
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }
    
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }
    
    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }
    
    /**
     * Clear log file
     */
    public function clear(): void
    {
        file_put_contents($this->logFile, '');
    }
    
    /**
     * Get recent log entries
     */
    public function getRecent(int $lines = 100): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $content = file_get_contents($this->logFile);
        $allLines = explode("\n", trim($content));
        
        return array_slice($allLines, -$lines);
    }
}

/**
 * Global helper
 */
function pugo_log(): Logger
{
    return Logger::getInstance();
}

