<?php
namespace josemmo\Verifactu\Tests;

use RuntimeException;
use UXML\UXML;

class TestUtils {
    /**
     * Get XML file
     *
     * @param string $path Path to XML file
     *
     * @return UXML XML document
     *
     * @throws RuntimeException if failed to read file
     */
    public static function getXmlFile(string $path): UXML {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException("Failed to read $path");
        }
        return UXML::fromString($data);
    }
}
