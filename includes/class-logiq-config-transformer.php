<?php
/**
 * LogIQ Config Transformer Class
 *
 * @package LogIQ
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LogIQ_Config_Transformer
 */
class LogIQ_Config_Transformer {
    private $config_path;
    private $config_content;
    private $backup_path;

    /**
     * Constructor
     */
    public function __construct($config_path) {
        $this->config_path = $config_path;
        $this->backup_path = $config_path . '.bak';
        
        if (!file_exists($config_path) || !is_readable($config_path)) {
            throw new Exception('Config file not found or not readable');
        }
        
        $this->config_content = file_get_contents($config_path);
        
        // Create backup if it doesn't exist
        if (!file_exists($this->backup_path)) {
            copy($config_path, $this->backup_path);
        }
    }

    /**
     * Check if a constant exists
     */
    public function exists($name) {
        $pattern = $this->get_define_pattern($name);
        return preg_match($pattern, $this->config_content) === 1;
    }

    /**
     * Get constant value
     */
    public function get_value($name) {
        if (!$this->exists($name)) {
            return null;
        }

        $pattern = $this->get_define_pattern($name);
        if (preg_match($pattern, $this->config_content, $matches)) {
            $value = trim($matches[1]);
            return $this->parse_value($value);
        }

        return null;
    }

    /**
     * Update constant value
     */
    public function update($name, $value) {
        try {
            // Create a new backup before each update
            if (!copy($this->config_path, $this->backup_path)) {
                throw new Exception('Failed to create backup file');
            }
            
            $normalized_value = $this->normalize_value($value);
            
            if ($this->exists($name)) {
                // Update existing constant
                $pattern = $this->get_define_pattern($name);
                $replacement = "define('$name', $normalized_value);";
                $new_content = preg_replace($pattern, $replacement, $this->config_content);
                
                if ($new_content === null) {
                    throw new Exception('Regex error while updating constant');
                }
                
                $this->config_content = $new_content;
            } else {
                // Find the best place to insert the new constant
                $insertion_point = $this->find_insertion_point();
                $new_define = "\ndefine('$name', $normalized_value);";
                
                $this->config_content = substr_replace(
                    $this->config_content,
                    $new_define,
                    $insertion_point,
                    0
                );
            }


            $write_result = file_put_contents($this->config_path, $this->config_content);
            
            if ($write_result === false) {
                throw new Exception('Failed to write config file');
            }
            
            return true;

        } catch (Exception $e) {
            error_log("LogIQ Debug - Error in update: " . $e->getMessage());
            // Restore from backup if something went wrong
            if (file_exists($this->backup_path)) {
                error_log("LogIQ Debug - Restoring from backup");
                copy($this->backup_path, $this->config_path);
            }
            throw $e;
        }
    }

    /**
     * Find the best place to insert new constants
     */
    private function find_insertion_point() {
        // Try to find the last define statement
        if (preg_match_all('/define\s*\([^;]+;\s*/s', $this->config_content, $matches, PREG_OFFSET_CAPTURE)) {
            $last_define = end($matches[0]);
            return $last_define[1] + strlen($last_define[0]);
        }
        
        // If no defines found, insert after opening PHP tag
        if (preg_match('/<\?php\s+/s', $this->config_content, $matches, PREG_OFFSET_CAPTURE)) {
            return $matches[0][1] + strlen($matches[0][0]);
        }
        
        // Fallback to start of file
        return 0;
    }

    /**
     * Get regex pattern for matching define statements
     */
    private function get_define_pattern($name) {
        return '/define\s*\(\s*[\'"]{1}' . preg_quote($name, '/') . '[\'"]{1}\s*,\s*([^)]+?)\s*\)\s*;/s';
    }

    /**
     * Parse config value
     */
    private function parse_value($value) {
        $value = trim($value);
        
        // Handle boolean values
        if ($value === 'true' || $value === 'false') {
            return $value === 'true';
        }
        
        // Handle numeric values
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        // Handle string values
        if (preg_match('/^[\'"](.+)[\'"]$/', $value, $matches)) {
            return $matches[1];
        }
        
        return $value;
    }

    /**
     * Normalize value for writing to config
     */
    private function normalize_value($value) {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }
        
        if (is_null($value)) {
            return 'null';
        }
        
        return $value;
    }
} 